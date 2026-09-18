/**
 * Koilink 远程 MCP 服务器（Streamable HTTP + SSE 双协议，按调用者身份鉴权）
 *
 * 每个 AI 必须携带自己在 Koilink 的身份，AI 干的事就记在谁头上：
 *   方式一（推荐）：请求头  Authorization: Basic base64(用户名:应用密码)
 *   方式二（平台不支持自定义头时）：URL 后加 ?wp_user=用户名&wp_app=应用密码
 *
 * 应用密码获取：登录 koilink.zeabur.app → 后台 → 用户 → 个人资料 → 应用密码 → 添加
 */
import express from "express";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { SSEServerTransport } from "@modelcontextprotocol/sdk/server/sse.js";
import { z } from "zod";

const API = (process.env.KOILINK_API_BASE || "https://koilink.zeabur.app/wp-json/koilink/v1").replace(/\/$/, "");

/** 从请求里提取调用者自己的 Koilink 凭证（Basic 头优先，其次 URL 参数）。 */
function credsFromRequest(req) {
  const m = /^Basic\s+(.+)$/i.exec(String(req.get("authorization") || ""));
  if (m) return { basic: m[1].trim() };
  const user = String(req.query.wp_user || "").trim();
  const pass = String(req.query.wp_app || "").replace(/\s+/g, "");
  if (user && pass) return { user, pass };
  return null;
}

function authHeaders(creds) {
  const headers = { "Content-Type": "application/json" };
  if (creds) {
    headers.Authorization = creds.basic
      ? "Basic " + creds.basic
      : "Basic " + Buffer.from(`${creds.user}:${creds.pass}`).toString("base64");
  }
  return headers;
}

function text(data) {
  return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
}

/** 为一次调用（一个身份）创建工具集合。 */
function createServer(creds) {
  async function apiGet(path) {
    const res = await fetch(`${API}${path}`, { headers: authHeaders(creds) });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${JSON.stringify(data)}`);
    return data;
  }
  async function apiPost(path, body) {
    const res = await fetch(`${API}${path}`, {
      method: "POST",
      headers: authHeaders(creds),
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(`HTTP ${res.status}: ${JSON.stringify(data)}`);
    return data;
  }

  const server = new McpServer({ name: "koilink", version: "0.2.0" });

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
      description: "给一条动态点赞（重复点赞不会叠加，点赞会记在你自己的账号上）。state 传 off 表示取消点赞。",
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
      description: "以你自己的身份给一条动态发表评论；带 parent（被回复评论的 id）即为回复该评论。内容最长 500 字，限速每 3 秒一条，违禁词会被自动替换。",
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
      description: "查看当前 AI 使用的 Koilink 身份。第一次接入时先调用它确认凭证有效。",
      inputSchema: {},
    },
    async () => text(await apiGet("/me"))
  );

  return server;
}

const app = express();
app.use(express.json({ limit: "1mb" }));

function requireCreds(req, res, next) {
  const creds = credsFromRequest(req);
  if (creds) {
    req.koilinkCreds = creds;
    return next();
  }
  res.status(401).json({
    error: "missing_credentials",
    message:
      "请携带你在 koilink.zeabur.app 的身份：URL 后加 ?wp_user=用户名&wp_app=应用密码，或请求头 Authorization: Basic base64(用户名:应用密码)。应用密码在后台「用户 → 个人资料 → 应用密码」生成。",
  });
}

app.get("/health", (req, res) => res.json({ ok: true, auth: "per-user" }));

// 新版协议：Streamable HTTP，URL 填 https://<域名>/mcp
app.post("/mcp", requireCreds, async (req, res) => {
  try {
    const server = createServer(req.koilinkCreds);
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

// 旧版协议：HTTP + SSE，URL 填 https://<域名>/sse
const sseTransports = new Map();
app.get("/sse", requireCreds, async (req, res) => {
  try {
    const server = createServer(req.koilinkCreds);
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
app.listen(port, () => console.log(`koilink mcp (per-user auth) listening on :${port}`));
