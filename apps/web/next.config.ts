import type { NextConfig } from "next";

const SERVER_PORT = process.env.SERVER_PORT || "8000";

const nextConfig: NextConfig = {
  // MỘT biến SERVER_PORT điều khiển cả hai đường tới backend:
  // - rewrites bên dưới (proxy /api, /media qua Next sang Laravel API port 8000);
  // - serverOrigin() trong lib/api.ts (client gọi THẲNG backend cho upload,
  //   bootstrap token) - phía client chỉ đọc được biến NEXT_PUBLIC_*.
  env: {
    NEXT_PUBLIC_SERVER_PORT:
      process.env.SERVER_PORT ?? process.env.NEXT_PUBLIC_SERVER_PORT ?? "8000",
  },
  devIndicators: {
    position: "bottom-right",
  },
  experimental: {
    proxyTimeout: 10 * 60 * 1000,
    proxyClientMaxBodySize: 2 * 1024 * 1024 * 1024,
  },
  async rewrites() {
    return [
      {
        source: "/api/:path*",
        destination: `http://localhost:${SERVER_PORT}/api/:path*`,
      },
      {
        source: "/media/:path*",
        destination: `http://localhost:${SERVER_PORT}/media/:path*`,
      },
    ];
  },
};

export default nextConfig;
