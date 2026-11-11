import http from "node:http";
import { readFile } from "node:fs/promises";
import portal from "./api/portal.js";
import sync from "./api/sync.js";
const files = {
    "/camera.js": ["camera.js", "text/javascript"],
    "/": ["index.html", "text/html"],
    "/portal.js": ["portal.js", "text/javascript"],
    "/portal.css": ["portal.css", "text/css"],
};
http.createServer(async (req, res) => {
    try {
        const url = new URL(req.url, "http://localhost");
        req.query = Object.fromEntries(url.searchParams);
        res.status = (code) => {
            res.statusCode = code;
            return res;
        };
        res.json = (data) => {
            res.setHeader("Content-Type", "application/json");
            res.end(JSON.stringify(data));
        };
        res.send = (data) => res.end(data);
        if (["/api/portal", "/api/sync"].includes(url.pathname)) {
            let raw = "";
            for await (const chunk of req) {
                raw += chunk;
                if (raw.length > 20000) {
                    res.statusCode = 413;
                    return res.end();
                }
            }
            if (raw) req.body = JSON.parse(raw);
            return await (url.pathname.endsWith("portal") ? portal : sync)(
                req,
                res,
            );
        }
        const file = files[url.pathname];
        if (!file) {
            res.statusCode = 404;
            return res.end();
        }
        res.setHeader("Content-Type", file[1]);
        res.setHeader("Cache-Control", "no-store");
        res.end(
            await readFile(new URL("./public/" + file[0], import.meta.url)),
        );
    } catch {
        res.statusCode = 500;
        res.end("Serviço indisponível.");
    }
}).listen(Number(process.env.PORT || 3000), "127.0.0.1", () =>
    console.log("Portal: http://127.0.0.1:" + (process.env.PORT || 3000)),
);
