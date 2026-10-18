#!/usr/bin/env python3
"""
Starts FTP, FTPS, SFTP and HTTP servers for the PHPUnit tests of this plugin.

Use bin/test-servers.sh to start and stop them, not this script directly.

- Runs as the actual user, never as root, on ports above 1024 and only on 127.0.0.1.
- Every server uses a copy of tests/Data/ftp in <dir>/root, so tests can change files.
- Writes <dir>/servers.json with the available servers. A server whose Python module is
  missing is listed with the reason, so the tests can skip it with a helpful message.

Servers (ports can be changed via environment variables):
- ftp           EFML_TEST_FTP_PORT (2121)            needs python3-pyftpdlib
- ftp_nat       EFML_TEST_FTP_NAT_PORT (2122)        returns an unreachable address for passive mode (like behind NAT)
- ftps          EFML_TEST_FTPS_PORT (2990)           needs python3-pyftpdlib, python3-openssl
- ftps_noreuse  EFML_TEST_FTPS_NOREUSE_PORT (2991)   FTPS without TLS session resumption
- sftp          EFML_TEST_SFTP_PORT (2222)           needs python3-paramiko
- http          EFML_TEST_HTTP_PORT (8181)           HTTP and WordPress REST API, see http_server.py
- passive mode of all FTP servers: 100 ports from EFML_TEST_FTP_PASSIVE_PORTS_START (30000)
"""

import argparse
import json
import logging
import os
import shutil
import signal
import socket
import stat
import sys
import threading

HOST = '127.0.0.1'
LOGIN = 'efml'
PASSWORD = 'efml-test-password'


def port(name, default):
    return int(os.environ.get(name, default))


def free(port_number):
    """Return whether the port is free, so a used port results in a clear message."""
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as test:
        test.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        try:
            test.bind((HOST, port_number))
            return True
        except OSError:
            return False


def create_certificate(directory):
    """Create a self-signed certificate for the FTPS servers."""
    import datetime

    from cryptography import x509
    from cryptography.hazmat.primitives import hashes, serialization
    from cryptography.hazmat.primitives.asymmetric import rsa
    from cryptography.x509.oid import NameOID

    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    name = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, 'localhost')])
    now = datetime.datetime.now(datetime.timezone.utc)
    cert = (
        x509.CertificateBuilder()
        .subject_name(name)
        .issuer_name(name)
        .public_key(key.public_key())
        .serial_number(x509.random_serial_number())
        .not_valid_before(now - datetime.timedelta(minutes=5))
        .not_valid_after(now + datetime.timedelta(days=1))
        .sign(key, hashes.SHA256())
    )
    path = os.path.join(directory, 'ftps.pem')
    with open(path, 'wb') as file:
        file.write(key.private_bytes(serialization.Encoding.PEM, serialization.PrivateFormat.TraditionalOpenSSL, serialization.NoEncryption()))
        file.write(cert.public_bytes(serialization.Encoding.PEM))
    return path


def start_ftp(root, port_number, tls=None, certificate=None, masquerade=None):
    """Create an FTP server. tls is None, 'reuse' or 'noreuse'. masquerade is the address returned for passive mode."""
    from pyftpdlib.authorizers import DummyAuthorizer
    from pyftpdlib.servers import FTPServer

    authorizer = DummyAuthorizer()
    authorizer.add_user(LOGIN, PASSWORD, root, perm='elradfmwMT')

    if tls is None:
        from pyftpdlib.handlers import FTPHandler

        class Handler(FTPHandler):
            pass
    else:
        import contextlib

        from OpenSSL import SSL
        from pyftpdlib.handlers import TLS_DTPHandler, TLS_FTPHandler

        class DataHandler(TLS_DTPHandler):
            """
            Data connection without the busy loop of pyftpdlib during the TLS handshake.

            If the client opens the data connection, but does not start TLS (like PHP without
            reusable TLS session), pyftpdlib waits for reading and writing. As the socket is
            always writable, the IOLoop wakes up all the time and uses 100% CPU until the client
            closes the connection. This slows down the tests and the whole computer.
            """

            @contextlib.contextmanager
            def _handle_ssl_want_rw(self):
                with super()._handle_ssl_want_rw():
                    yield
                # only wait for the data of the client, while the handshake needs it.
                # pyftpdlib restores the wanted events after the handshake.
                if self._ssl_accepting and self._ssl_want_read:
                    self.modify_ioloop_events(self.ioloop.READ)

        class Handler(TLS_FTPHandler):
            def ftp_QUIT(self, line):
                # pyftpdlib waits with the end of the connection for an open data connection.
                # A data connection without TLS handshake (see above) would never end, so the client
                # would wait for the end of the connection until its timeout.
                data_channel = self.data_channel
                if data_channel is not None and not getattr(data_channel, '_ssl_established', True):
                    data_channel.close()
                return super().ftp_QUIT(line)

        # close idle data connections after 10 instead of 300 seconds, so a hanging data connection does not
        # stay open for long.
        DataHandler.timeout = 10
        Handler.dtp_handler = DataHandler

        Handler.certfile = certificate
        Handler.tls_control_required = True
        Handler.tls_data_required = True

        if tls == 'noreuse':
            # TLS 1.2 without session tickets and without session cache: the server sends an empty
            # session ID, so the client gets no session it could reuse for the data connection.
            # PHP stops then with "failed to retrieve the existing SSL session".
            context = SSL.Context(SSL.TLS_SERVER_METHOD)
            context.use_privatekey_file(certificate)
            context.use_certificate_chain_file(certificate)
            context.set_max_proto_version(SSL.TLS1_2_VERSION)
            context.set_options(SSL.OP_NO_TICKET)
            context.set_session_cache_mode(SSL.SESS_CACHE_OFF)
            Handler.ssl_context = context

    Handler.authorizer = authorizer
    Handler.passive_ports = range(port('EFML_TEST_FTP_PASSIVE_PORTS_START', 30000), port('EFML_TEST_FTP_PASSIVE_PORTS_START', 30000) + 100)
    Handler.banner = 'EFML test server'
    # answer a failed login without the default delay of 3 seconds, which only slows down the tests.
    Handler.auth_failed_timeout = 0.1
    if masquerade:
        Handler.masquerade_address = masquerade
    # all FTP servers share one IOLoop of pyftpdlib, which main() runs once.
    return FTPServer((HOST, port_number), Handler)


def start_sftp(root, port_number):
    """Start an SFTP server in a thread, which allows password login and works in root."""
    import paramiko

    host_key = paramiko.RSAKey.generate(2048)

    def local(path):
        """Return the local path for a path of the SFTP server, which never leaves root."""
        path = os.path.normpath('/' + path.lstrip('/'))
        return os.path.join(root, path.lstrip('/'))

    def result(function):
        """Run the function and convert errors to SFTP error codes."""
        try:
            value = function()
            return paramiko.SFTP_OK if value is None else value
        except OSError as error:
            return paramiko.SFTPServer.convert_errno(error.errno)

    class Server(paramiko.ServerInterface):
        def check_auth_password(self, username, password):
            if username == LOGIN and password == PASSWORD:
                return paramiko.AUTH_SUCCESSFUL
            return paramiko.AUTH_FAILED

        def get_allowed_auths(self, username):
            return 'password'

        def check_channel_request(self, kind, chanid):
            return paramiko.OPEN_SUCCEEDED if 'session' == kind else paramiko.OPEN_FAILED_ADMINISTRATIVELY_PROHIBITED

        def check_channel_subsystem_request(self, channel, name):
            return super().check_channel_subsystem_request(channel, name)

        def check_channel_exec_request(self, channel, command):
            # commands are not supported: answer with an exit status, so the client does not hang.
            channel.send_exit_status(1)
            return True

    class Handle(paramiko.SFTPHandle):
        def stat(self):
            return result(lambda: paramiko.SFTPAttributes.from_stat(os.fstat(self.readfile.fileno())))

    class Sftp(paramiko.SFTPServerInterface):
        def canonicalize(self, path):
            return os.path.normpath('/' + path.lstrip('/'))

        def list_folder(self, path):
            def listing():
                entries = []
                for name in os.listdir(local(path)):
                    attributes = paramiko.SFTPAttributes.from_stat(os.stat(os.path.join(local(path), name)))
                    attributes.filename = name
                    entries.append(attributes)
                return entries
            return result(listing)

        def stat(self, path):
            return result(lambda: paramiko.SFTPAttributes.from_stat(os.stat(local(path))))

        def lstat(self, path):
            return result(lambda: paramiko.SFTPAttributes.from_stat(os.lstat(local(path))))

        def open(self, path, flags, attr):
            def opening():
                mode = 'rb'
                if flags & os.O_WRONLY:
                    mode = 'ab' if flags & os.O_APPEND else 'wb'
                elif flags & os.O_RDWR:
                    mode = 'a+b' if flags & os.O_APPEND else 'r+b'
                    if flags & os.O_CREAT and not os.path.exists(local(path)):
                        mode = 'w+b'
                file = open(local(path), mode)
                handle = Handle(flags)
                handle.filename = local(path)
                handle.readfile = file
                handle.writefile = file
                return handle
            return result(opening)

        def remove(self, path):
            return result(lambda: os.remove(local(path)))

        def rename(self, oldpath, newpath):
            return result(lambda: os.rename(local(oldpath), local(newpath)))

        def mkdir(self, path, attr):
            return result(lambda: os.mkdir(local(path)))

        def rmdir(self, path):
            return result(lambda: os.rmdir(local(path)))

        def chattr(self, path, attr):
            return paramiko.SFTP_OK

    def serve(client):
        transport = paramiko.Transport(client)
        transport.add_server_key(host_key)
        transport.set_subsystem_handler('sftp', paramiko.SFTPServer, Sftp)
        transport.start_server(server=Server())
        while transport.is_active():
            transport.join(1)

    listener = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    listener.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    listener.bind((HOST, port_number))
    listener.listen(10)

    def accept():
        while True:
            client, _ = listener.accept()
            threading.Thread(target=serve, args=(client,), daemon=True).start()

    threading.Thread(target=accept, daemon=True).start()


def main():
    parser = argparse.ArgumentParser(description='Test servers for the PHPUnit tests.')
    parser.add_argument('--dir', required=True, help='Directory for files, certificate and servers.json.')
    parser.add_argument('--data', required=True, help='Directory with the test files.')
    arguments = parser.parse_args()

    if hasattr(os, 'geteuid') and 0 == os.geteuid():
        sys.exit('The test servers must not run as root.')

    # only show warnings of the servers, not every connection.
    logging.basicConfig(level=logging.WARNING)
    logging.getLogger('pyftpdlib').setLevel(logging.WARNING)
    logging.getLogger('paramiko').setLevel(logging.WARNING)

    directory = os.path.abspath(arguments.dir)
    root = os.path.join(directory, 'root')
    shutil.rmtree(root, ignore_errors=True)
    shutil.copytree(arguments.data, root)
    os.chmod(directory, stat.S_IRWXU)

    servers = {}
    definitions = (
        ('ftp', 'EFML_TEST_FTP_PORT', 2121, 'ftp'),
        ('ftp_nat', 'EFML_TEST_FTP_NAT_PORT', 2122, 'ftp'),
        ('ftps', 'EFML_TEST_FTPS_PORT', 2990, 'ftps'),
        ('ftps_noreuse', 'EFML_TEST_FTPS_NOREUSE_PORT', 2991, 'ftps'),
        ('sftp', 'EFML_TEST_SFTP_PORT', 2222, 'sftp'),
        ('http', 'EFML_TEST_HTTP_PORT', 8181, 'http'),
    )
    certificate = None
    ftp_servers = []
    for name, variable, default, scheme in definitions:
        port_number = port(variable, default)
        entry = {'scheme': scheme, 'host': HOST, 'port': port_number, 'login': LOGIN, 'password': PASSWORD, 'root': root}
        try:
            if not free(port_number):
                raise RuntimeError(f'Port {port_number} is already in use (change it with {variable}).')
            if 'http' == name:
                # only uses the standard library of Python.
                from http_server import start_http
                start_http(root, HOST, port_number, LOGIN, PASSWORD)
            elif 'sftp' == name:
                try:
                    import paramiko  # noqa: F401
                except ImportError:
                    raise RuntimeError('Python module paramiko is missing (Debian: apt install python3-paramiko).')
                start_sftp(root, port_number)
            else:
                try:
                    import pyftpdlib  # noqa: F401
                except ImportError:
                    raise RuntimeError('Python module pyftpdlib is missing (Debian: apt install python3-pyftpdlib).')
                tls = None
                if name not in ('ftp', 'ftp_nat'):
                    try:
                        import OpenSSL  # noqa: F401
                    except ImportError:
                        raise RuntimeError('Python module OpenSSL is missing (Debian: apt install python3-openssl).')
                    if certificate is None:
                        certificate = create_certificate(directory)
                    tls = 'noreuse' if 'ftps_noreuse' == name else 'reuse'
                # 192.0.2.1 is reserved for documentation and not reachable, like an internal address behind NAT.
                ftp_servers.append(start_ftp(root, port_number, tls, certificate, '192.0.2.1' if 'ftp_nat' == name else None))
            entry['available'] = True
        except Exception as error:  # every problem only disables this one server.
            entry = {'available': False, 'reason': str(error)}
        servers[name] = entry

    # run the IOLoop of all FTP servers.
    if ftp_servers:
        threading.Thread(target=ftp_servers[0].serve_forever, daemon=True).start()

    # write the file at last: the start script waits for it.
    with open(os.path.join(directory, 'servers.json.tmp'), 'w') as file:
        json.dump(servers, file, indent=2)
    os.replace(os.path.join(directory, 'servers.json.tmp'), os.path.join(directory, 'servers.json'))

    for name, entry in servers.items():
        print(f"{name}: {'port ' + str(entry['port']) if entry.get('available') else entry['reason']}", flush=True)

    signal.signal(signal.SIGTERM, lambda *_: sys.exit(0))
    threading.Event().wait()


if __name__ == '__main__':
    main()
