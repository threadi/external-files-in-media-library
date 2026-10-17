#!/usr/bin/env bash
#
# Starts and stops the FTP, FTPS, SFTP and HTTP servers for the PHPUnit tests.
#
# Usage:
#   bin/test-servers.sh start    Start the servers in the background.
#   bin/test-servers.sh stop     Stop the servers and remove their files.
#   bin/test-servers.sh status   Show which servers are available.
#
# The servers run as the actual user on ports above 1024, only on 127.0.0.1. The script never runs as root.
# Needs python3 with pyftpdlib, OpenSSL and paramiko (Debian: apt install python3-pyftpdlib python3-openssl python3-paramiko).
# Without them, the tests for the affected servers are skipped.
#
# The directory for the files of the servers can be changed with EFML_TEST_SERVERS_DIR.
# The ports can be changed with EFML_TEST_FTP_PORT, EFML_TEST_FTP_NAT_PORT, EFML_TEST_FTPS_PORT, EFML_TEST_FTPS_NOREUSE_PORT, EFML_TEST_SFTP_PORT,
# EFML_TEST_HTTP_PORT and EFML_TEST_FTP_PASSIVE_PORTS_START (100 ports for the passive mode).

set -euo pipefail

if [ "$(id -u)" = "0" ]; then
  echo "Error: the test servers must not run as root." >&2
  exit 1
fi

cd "$(dirname "$0")/.."

DIR="${EFML_TEST_SERVERS_DIR:-${TMPDIR:-/tmp}/efml-test-servers-$(id -un)}"
PID_FILE="$DIR/servers.pid"

stop() {
  if [ -f "$PID_FILE" ]; then
    PID="$(cat "$PID_FILE")"
    kill "$PID" 2>/dev/null || true

    # wait until the servers have ended, so their ports are free for a new start.
    for _ in $(seq 1 50); do
      kill -0 "$PID" 2>/dev/null || break
      sleep 0.1
    done
    kill -9 "$PID" 2>/dev/null || true
  fi
  rm -rf "$DIR"
}

start() {
  # stop servers of a previous run.
  stop

  if ! command -v python3 > /dev/null; then
    echo "Note: python3 is missing, the tests for FTP, FTPS and SFTP will be skipped."
    return 0
  fi

  mkdir -p "$DIR"
  chmod 700 "$DIR"
  nohup python3 tests/Servers/test-servers.py --dir "$DIR" --data tests/Data/ftp > "$DIR/servers.log" 2>&1 &
  echo $! > "$PID_FILE"

  # wait until the servers are ready.
  for _ in $(seq 1 100); do
    if [ -f "$DIR/servers.json" ]; then
      cat "$DIR/servers.log"
      return 0
    fi
    if ! kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
      echo "Note: the test servers could not be started, the tests for FTP, FTPS and SFTP will be skipped:"
      cat "$DIR/servers.log"
      return 0
    fi
    sleep 0.1
  done
  echo "Note: the test servers did not start within 10 seconds."
}

status() {
  if [ -f "$DIR/servers.json" ] && kill -0 "$(cat "$PID_FILE" 2>/dev/null)" 2>/dev/null; then
    cat "$DIR/servers.log"
  else
    echo "The test servers are not running."
  fi
}

case "${1:-}" in
  start) start ;;
  stop) stop ;;
  status) status ;;
  *) echo "Usage: $0 start | stop | status" >&2; exit 1 ;;
esac
