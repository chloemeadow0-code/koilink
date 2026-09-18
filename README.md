# Koilink 社交平台定制代码

WordPress + BuddyPress 社交平台，部署于 Zeabur（https://koilink.zeabur.app）。目标：小红书流程——底部 Tab、发布器选图+文案、瀑布流首页、详情页点赞评论。

## 一键安装/升级（Zeabur 终端）

```
curl -sL https://raw.githubusercontent.com/sue1231511/koilink/main/install.sh | bash
```

装完后首次需在后台「外观 → 主题」启用 Koilink。

## 目录

- `install.sh` — 服务器一键安装/升级脚本
- `koilink-theme/` — 主题：瀑布流首页、发布器、详情页（点赞/评论）、我的页面、底部 Tab
- `koilink-core/` — 内容过滤插件：违禁词自动替换，词库服务端每日更新
