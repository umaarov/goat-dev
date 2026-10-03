# local stand-in for api.telegram.org: records what the app and Alertmanager would have sent
import email
import email.policy
import hashlib
import json
import time
import urllib.parse
from http.server import BaseHTTPRequestHandler, HTTPServer

messages = []
files = []


class Handler(BaseHTTPRequestHandler):
    def _send(self, code, body, raw=False):
        data = body if raw else json.dumps(body).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/octet-stream" if raw else "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def _fields(self, raw):
        ctype = self.headers.get("Content-Type", "")
        if ctype.startswith("multipart/form-data"):
            msg = email.message_from_bytes(("Content-Type: %s\r\n\r\n" % ctype).encode() + raw, policy=email.policy.HTTP)
            fields, document = {}, None
            for part in msg.iter_parts():
                name = part.get_param("name", header="content-disposition")
                if part.get_filename():
                    body = part.get_payload(decode=True)
                    files.append(body)
                    document = {"filename": part.get_filename(), "size": len(body), "sha256": hashlib.sha256(body).hexdigest(), "index": len(files) - 1}
                else:
                    fields[name] = part.get_content()
            return fields, document
        try:
            return json.loads(raw), None
        except ValueError:
            return {k: v[0] for k, v in urllib.parse.parse_qs(raw.decode()).items()}, None

    def do_POST(self):
        length = int(self.headers.get("Content-Length", 0))
        payload, document = self._fields(self.rfile.read(length))
        if not (self.path.endswith("/sendMessage") or self.path.endswith("/sendDocument")):
            return self._send(404, {"ok": False})
        entry = {"path": self.path, "payload": payload}
        if document:
            entry["document"] = document
        messages.append(entry)
        print(json.dumps({"telegram_mock": payload, "document": document}), flush=True)
        # same shape as the real API: clients read result.chat.id
        self._send(200, {"ok": True, "result": {
            "message_id": len(messages),
            "date": int(time.time()),
            "chat": {"id": int(payload.get("chat_id", 0)), "type": "supergroup"},
            "text": payload.get("text", ""),
        }})

    def do_GET(self):
        if self.path == "/messages":
            return self._send(200, messages)
        if self.path.startswith("/files/"):
            return self._send(200, files[int(self.path.split("/")[-1])], raw=True)
        if self.path == "/reset":
            messages.clear()
            files.clear()
            return self._send(200, {"ok": True})
        self._send(200, {"ok": True})

    def log_message(self, *args):
        pass


HTTPServer(("0.0.0.0", 8081), Handler).serve_forever()
