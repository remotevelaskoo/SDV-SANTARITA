import type { Metadata } from "next";
import { Geist } from "next/font/google";
import { headers } from "next/headers";
import "./globals.css";

const geist = Geist({
  variable: "--font-geist",
  subsets: ["latin"],
});

export async function generateMetadata(): Promise<Metadata> {
  const requestHeaders = await headers();
  const host = requestHeaders.get("host") ?? "localhost:3000";
  const protocol =
    requestHeaders.get("x-forwarded-proto") ??
    (host.startsWith("localhost") ? "http" : "https");
  const baseUrl = new URL(`${protocol}://${host}`);
  const description =
    "Protótipo funcional dos fluxos de terminal, login, perfis e operação do SDV Access.";

  return {
    metadataBase: baseUrl,
    title: "SDV Access — Protótipo navegável",
    description,
    icons: {
      icon: "/favicon.svg",
      shortcut: "/favicon.svg",
    },
    openGraph: {
      title: "SDV Access",
      description: "Protótipo navegável de controle de acesso",
      images: [new URL("/og.png", baseUrl).toString()],
      type: "website",
    },
    twitter: {
      card: "summary_large_image",
      title: "SDV Access",
      description: "Protótipo navegável de controle de acesso",
      images: [new URL("/og.png", baseUrl).toString()],
    },
  };
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="pt-BR">
      <body className={geist.variable}>{children}</body>
    </html>
  );
}
