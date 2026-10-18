"""
HTTP server for the PHPUnit tests of this plugin, used by test-servers.py.

Needs only the Python standard library. Serves the files in <root> (a copy of tests/Data/ftp):

- /files/<path>                     the file with Content-Type, Content-Length and Last-Modified,
                                    a directory as HTML page with links to its files and subdirectories
- /protected/<path>                 like /files/, but only with HTTP Basic authentication
- /redirect/<path>                  redirects (302) to /files/<path>
- /disposition/<path>               like /files/, with "Content-Disposition: attachment; filename=renamed-<name>"
- /no-content-type/<path>           like /files/, without Content-Type
- /get-fails/<path>                 HEAD like /files/, GET answers with 500
- /status/<code>/<name>             answers with the HTTP status <code>
- /sites/<dir>/wp-json/wp/v2/media  WordPress REST API with the files of <root>/<dir> ("_" for <root>)
- /plain-sites/<dir>/?rest_route=/wp/v2/media
                                    the same API without /wp-json (like WordPress without pretty permalinks)
"""

import base64
import email.utils
import html
import json
import mimetypes
import os
import threading
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

# file types of the test files, independent of the system configuration.
mimetypes.add_type('image/jpeg', '.jpg')
mimetypes.add_type('image/png', '.png')
mimetypes.add_type('image/gif', '.gif')
mimetypes.add_type('text/plain', '.txt')
mimetypes.add_type('application/pdf', '.pdf')


def start_http(root, host, port_number, login, password):
    """Start the HTTP server in a thread."""

    class Handler(BaseHTTPRequestHandler):
        server_version = 'EFML-Test-Server'
        protocol_version = 'HTTP/1.1'

        def log_message(self, format, *args):
            # no output for each request.
            pass

        def local_path(self, path):
            """Return the local path for a path in the URL, which never leaves root."""
            path = urllib.parse.unquote(path)
            path = os.path.normpath('/' + path.lstrip('/'))
            return os.path.join(root, path.lstrip('/'))

        def send(self, status, headers=None, body=b''):
            self.send_response(status)
            for name, value in (headers or {}).items():
                self.send_header(name, value)
            self.send_header('Content-Length', str(len(body)))
            self.end_headers()
            if 'HEAD' != self.command:
                self.wfile.write(body)

        def send_json(self, status, data, headers=None):
            all_headers = {'Content-Type': 'application/json; charset=UTF-8'}
            all_headers.update(headers or {})
            self.send(status, all_headers, json.dumps(data).encode('utf-8'))

        def send_path(self, path, content_type=True, disposition=None):
            """Send a file or the HTML listing of a directory."""
            local = self.local_path(path)
            if os.path.isdir(local):
                if not path.endswith('/'):
                    self.send(301, {'Location': self.path + '/'})
                    return
                links = []
                for name in sorted(os.listdir(local)):
                    target = name + '/' if os.path.isdir(os.path.join(local, name)) else name
                    links.append('<li><a href="%s">%s</a></li>' % (urllib.parse.quote(target), html.escape(target)))
                body = ('<!DOCTYPE html><html><body><ul>%s</ul></body></html>' % ''.join(links)).encode('utf-8')
                self.send(200, {'Content-Type': 'text/html; charset=utf-8'}, body)
                return
            if not os.path.isfile(local):
                self.send(404, {'Content-Type': 'text/plain'}, b'Not found')
                return
            with open(local, 'rb') as file:
                body = file.read()
            headers = {'Last-Modified': email.utils.formatdate(os.path.getmtime(local), usegmt=True)}
            if content_type:
                headers['Content-Type'] = mimetypes.guess_type(local)[0] or 'application/octet-stream'
            if disposition:
                headers['Content-Disposition'] = 'attachment; filename="%s"' % disposition
            self.send(200, headers, body)

        def media(self, base_url, directory, query):
            """Answer like the endpoint /wp/v2/media of the WordPress REST API."""
            local = root if '_' == directory else self.local_path(directory)
            if not os.path.isdir(local):
                self.send_json(404, {'code': 'rest_no_route', 'message': 'No route was found.'})
                return
            names = sorted(name for name in os.listdir(local) if os.path.isfile(os.path.join(local, name)))
            per_page = int(query.get('per_page', ['10'])[0])
            if per_page < 1 or per_page > 100:
                # like WordPress: per_page must be between 1 and 100.
                self.send_json(400, {'code': 'rest_invalid_param', 'message': 'Invalid parameter(s): per_page'})
                return
            page = max(1, int(query.get('page', ['1'])[0]))
            total_pages = max(1, -(-len(names) // per_page))
            headers = {'X-WP-Total': str(len(names)), 'X-WP-TotalPages': str(total_pages)}
            if page > total_pages:
                # like WordPress: a page after the last one results in 400.
                self.send_json(400, {'code': 'rest_post_invalid_page_number', 'message': 'The page number requested is larger than the number of pages available.'}, headers)
                return
            files_path = '/files/' + ('' if '_' == directory else directory.strip('/') + '/')
            items = []
            for index, name in enumerate(names[(page - 1) * per_page:page * per_page], start=(page - 1) * per_page + 1):
                path = os.path.join(local, name)
                items.append({
                    'id': index,
                    'date': '2026-01-%02dT10:00:00' % min(28, index),
                    'type': 'attachment',
                    'title': {'rendered': name},
                    'mime_type': mimetypes.guess_type(path)[0] or 'application/octet-stream',
                    'source_url': base_url + files_path + urllib.parse.quote(name),
                    'media_details': {'filesize': os.path.getsize(path)},
                })
            self.send_json(200, items, headers)

        def handle_request(self):
            parsed = urllib.parse.urlsplit(self.path)
            query = urllib.parse.parse_qs(parsed.query)
            path = parsed.path
            base_url = 'http://%s:%d' % (host, port_number)

            if path.startswith('/files/'):
                self.send_path(path[len('/files'):])
            elif path.startswith('/protected/'):
                expected = 'Basic ' + base64.b64encode(('%s:%s' % (login, password)).encode()).decode()
                if self.headers.get('Authorization') != expected:
                    self.send(401, {'WWW-Authenticate': 'Basic realm="EFML"', 'Content-Type': 'text/plain'}, b'Unauthorized')
                else:
                    self.send_path(path[len('/protected'):])
            elif path.startswith('/redirect/'):
                self.send(302, {'Location': base_url + '/files/' + path[len('/redirect/'):]})
            elif path.startswith('/disposition/'):
                self.send_path(path[len('/disposition'):], disposition='renamed-' + os.path.basename(path))
            elif path.startswith('/no-content-type/'):
                self.send_path(path[len('/no-content-type'):], content_type=False)
            elif path.startswith('/get-fails/'):
                if 'GET' == self.command:
                    self.send(500, {'Content-Type': 'text/plain'}, b'Error')
                else:
                    self.send_path(path[len('/get-fails'):])
            elif path.startswith('/status/'):
                parts = path.split('/')
                self.send(int(parts[2]), {'Content-Type': 'text/plain'}, b'Status')
            elif path.startswith('/sites/') and '/wp-json/wp/v2/media' in path:
                directory = path[len('/sites/'):path.index('/wp-json/')]
                self.media(base_url, directory, query)
            elif path.startswith('/plain-sites/') and query.get('rest_route', [''])[0].rstrip('/') == '/wp/v2/media':
                directory = path[len('/plain-sites/'):].strip('/')
                self.media(base_url, directory, query)
            elif (path.startswith('/sites/') and '/wp-json/' in path) or (path.startswith('/plain-sites/') and '/wp-json/' in path):
                # other routes of the REST API (and /wp-json on a website without pretty permalinks).
                self.send(404, {'Content-Type': 'text/html; charset=utf-8'}, b'<!DOCTYPE html><html><body>Not found</body></html>')
            elif path.startswith('/sites/') or path.startswith('/plain-sites/'):
                # the start page of the simulated website, like a WordPress website without links to files.
                self.send(200, {'Content-Type': 'text/html; charset=UTF-8'}, b'<!DOCTYPE html><html><body><h1>Test website</h1></body></html>')
            else:
                self.send(404, {'Content-Type': 'text/plain'}, b'Not found')

        def do_GET(self):
            self.handle_request()

        def do_HEAD(self):
            self.handle_request()

    server = ThreadingHTTPServer((host, port_number), Handler)
    server.daemon_threads = True
    threading.Thread(target=server.serve_forever, daemon=True).start()
