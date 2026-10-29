#!/usr/bin/env python3
"""Local WebDAV simulator for isolated CI only; not a test of the DEV Nextcloud server."""
from http.server import ThreadingHTTPServer,BaseHTTPRequestHandler
from pathlib import Path
from urllib.parse import unquote,urlsplit
import sys
root=Path(sys.argv[1]).resolve();root.mkdir(parents=True,exist_ok=True)
class Handler(BaseHTTPRequestHandler):
 def pathfile(self):
  p=(root/unquote(urlsplit(self.path).path).lstrip('/')).resolve()
  if p!=root and root not in p.parents:raise ValueError('Outside fixture root')
  return p
 def reply(self,status,data=b''):
  self.send_response(status);self.send_header('Content-Length',str(len(data)));self.end_headers();self.wfile.write(data)
 def do_MKCOL(self):
  p=self.pathfile()
  if p.exists():self.reply(405)
  else:p.mkdir(parents=True);self.reply(201)
 def do_PUT(self):
  p=self.pathfile();size=int(self.headers.get('Content-Length','0'))
  if self.headers.get('If-None-Match')=='*' and p.exists():self.reply(412);return
  p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(self.rfile.read(size));self.reply(201)
 def do_GET(self):
  p=self.pathfile();self.reply(200,p.read_bytes()) if p.is_file() else self.reply(404)
 def do_PROPFIND(self):self.reply(207,b'<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"/>')
 def log_message(self,*args):pass
ThreadingHTTPServer(('127.0.0.1',int(sys.argv[2]) if len(sys.argv)>2 else 8092),Handler).serve_forever()
