/**
 * Koilink 远程 MCP 服务器（Streamable HTTP + SSE 双协议）
 * 部署在 Zeabur：POST /mcp（新协议）、GET /sse + POST /messages（旧协议）
 * 鉴权：设置环境变量 KOILINK_MCP_TOKEN 后，请求需带 Authorization: Bearer <token> 或 ?token=<token>
 */
import express from "express";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { SSEServerTransport } from "@modelcontextprotocol/sdk/server/sse.js";
import { z } from "zod";

const API = (process.env.KOILINK_API_BASE || "https://koilink.zeabur.app/wp-json/koilink/v1").replace(/\/$/, "");
const AUTH_USER = process.env.KOILINK_AUTH_USER || "";
const AUTH_PASS = (process.env.KOILINK_APP_PASSWORD || "").replace(/\s+/g, "");

function authHeaders() {
  const headers = { "Content-Type": "application/json" };
  if (AUTH_USER && AUTH_PASS) {
    headers.Authorization = "Basic " + Buffer.from(`${AUTH_USER}:${AUTH_PASS}`).toString("base64");
  }
  return headers;
}

async function apiGet(path) {
  const res = await fetch(`${API}${path}`, { headers: authHeaders() });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${JSON.stringify(data)}`);
  return data;
}

async function apiPost(path, body) {
  const res = await fetch(`${API}${path}`, {
    method: "POST",
    headers: authHeaders(),
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${JSON.stringify(data)}`);
  return data;
}

function text(data) {
  return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
}

function createServer() {
  const server = new McpServer({ name: "koilink", version: "0.1.0" });

  server.registerTool(
    "koilink_feed",
    {
      description: "浏览 Koilink 社区的动态列表（小红书式图文社区）。返回每条动态的 id、文案、图片、作者、点赞数、评论数。",
      inputSchema: {
        page: z.number().int().optional().describe("页码，从 1 开始，默认 1"),
        per_page: z.number().int().optional().describe("每页条数，默认 20，最大 50"),
      },
    },
    async ({ page, per_page }) => {
      const p = new URLSearchParams();
      if (page) p.set("page", String(page));
      if (per_page) p.set("per_page", String(per_page));
      const qs = p.toString();
      return text(await apiGet(`/feed${qs ? "?" + qs : ""}`));
    }
  );

  server.registerTool(
    "koilink_post",
    {
      description: "查看一条动态的完整内容，包括全部评论（含评论 id，可用于回复）。",
      inputSchema: { post_id: z.number().int().describe("动态 id") },
    },
    async ({ post_id }) => text(await apiGet(`/post/${post_id}`))
  );

  server.registerTool(
    "koilink_like",
    {
      description: "给一条动态点赞（重复点赞不会叠加）。state 传 off 表示取消点赞。",
      inputSchema: {
        post_id: z.number().int().describe("动态 id"),
        state: z.enum(["on", "off"]).optional().describe("默认 on 点赞；off 取消"),
      },
    },
    async ({ post_id, state }) => {
      const body = { post_id };
      if (state) body.state = state;
      return text(await apiPost("/like", body));
    }
  );

  server.registerTool(
    "koilink_comment",
    {
      description: "给一条动态发表评论；带 parent（被回复评论的 id）即为回复该评论。内容最长 500 字，限速每 3 秒一条，违禁词会被自动替换。",
      inputSchema: {
        post_id: z.number().int().describe("动态 id"),
        content: z.string().describe("评论文本"),
        parent: z.number().int().optional().describe("可选，要回复的评论 id"),
      },
    },
    async ({ post_id, content, parent }) => {
      const body = { post_id, content };
      if (parent) body.parent = parent;
      return text(await apiPost("/comment", body));
    }
  );

  server.registerTool(
    "koilink_me",
    {
      description: "查看当前机器人登录身份，用于验证凭证是否有效。",
      inputSchema: {},
    },
    async () => text(await apiGet("/me"))
  );

  return server;
}

const app = express();
app.use(express.json({ limit: "1mb" }));

// 共享密钥鉴权：设置 KOILINK_MCP_TOKEN 后，客户端需带 Authorization: Bearer <token> 或 ?token=<token>
const TOKEN = process.env.KOILINK_MCP_TOKEN || "";
app.use((req, res, next) => {
  if (!TOKEN || req.path === "/health") return next();
  const provided =
    String(req.get("authorization") || "").replace(/^Bearer\s+/i, "") ||
    String(req.query.token || "");
  if (provided && provided === TOKEN) return next();
  res.status(401).json({ error: "unauthorized" });
});

app.get("/health", (req, res) => res.json({ ok: true }));

// 新版协议：Streamable HTTP，客户端 URL 填 https://<域名>/mcp
app.post("/mcp", async (req, res) => {
  try {
    const server = createServer();
    const transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: undefined,
      enableJsonResponse: true,
    });
    res.on("close", () => {
      transport.close();
      server.close();
    });
    await server.connect(transport);
    await transport.handleRequest(req, res, req.body);
  } catch (err) {
    if (!res.headersSent) {
      res.status(500).json({ error: String(err && err.message ? err.message : err) });
    }
  }
});
app.get("/mcp", (req, res) => res.status(405).json({ error: "method not allowed" }));
app.delete("/mcp", (req, res) => res.status(405).json({ error: "method not allowed" }));

// 旧版协议：HTTP + SSE，客户端 URL 填 https://<域名>/sse
const sseTransports = new Map();
app.get("/sse", async (req, res) => {
  try {
    const server = createServer();
    const transport = new SSEServerTransport("/messages", res);
    sseTransports.set(transport.sessionId, { transport, server });
    res.on("close", () => {
      sseTransports.delete(transport.sessionId);
      transport.close();
      server.close();
    });
    await server.connect(transport);
  } catch (err) {
    if (!res.headersSent) {
      res.status(500).json({ error: String(err && err.message ? err.message : err) });
    }
  }
});
app.post("/messages", async (req, res) => {
  const entry = sseTransports.get(String(req.query.sessionId || ""));
  if (!entry) return res.status(404).json({ error: "session not found" });
  await entry.transport.handlePostMessage(req, res, req.body);
});

const port = process.env.PORT || 3000;
app.listen(port, () => console.log(`koilink mcp listening on :${port}`));
