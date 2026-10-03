# local stand-in for api.telegram.org: records what Alertmanager would have sent
import json
import time
from http.server import BaseHTTPRequestHandler, HTTPServer

messages = []


class Handler(BaseHTTPRequestHandler):
    def _send(self, code, body):
        data = json.dumps(body).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_POST(self):
        length = int(self.headers.get("Content-Length", 0))
        raw = self.rfile.read(length).decode()
        try:
            payload = json.loads(raw)
        except ValueError:
            payload = {k: v[0] for k, v in __import__("urllib.parse").parse.parse_qs(raw).items()}
        if not self.path.endswith("/sendMessage"):
            return self._send(404, {"ok": False})
        messages.append({"path": self.path, "payload": payload})
        print(json.dumps({"telegram_mock": payload}), flush=True)
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
        if self.path == "/reset":
            messages.clear()
            return self._send(200, {"ok": True})
        self._send(200, {"ok": True})

    def log_message(self, *args):
        pass


HTTPServer(("0.0.0.0", 8081), Handler).serve_forever()
