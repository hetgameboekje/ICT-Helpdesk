import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { beheerUsers, rollen, rechten, apiKeys, mailQueue, systeemLogs } from "@/lib/mock-data";
import { Users, Key, Mail, ScrollText, Search, Plus, Copy, ShieldCheck, ShieldAlert, CheckCircle2, XCircle, Clock } from "lucide-react";
import { useState } from "react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/modules/beheer")({
  head: () => ({
    meta: [
      { title: "Beheer — Leen van Punt Intranet" },
      { name: "description", content: "Admin: gebruikers & rechten, API-sleutels, e-mailqueue, systeemlogs." },
      { property: "og:title", content: "Beheer — Leen van Punt Intranet" },
      { property: "og:description", content: "Admin-context: gebruikers & rechten, API-sleutels, e-mailqueue, systeemlogs." },
    ],
  }),
  component: BeheerPage,
});

const tabs = [
  { key: "users", label: "Gebruikers & rechten", icon: Users },
  { key: "api", label: "API-sleutels", icon: Key },
  { key: "mail", label: "E-mailqueue", icon: Mail },
  { key: "logs", label: "Logs", icon: ScrollText },
] as const;

function BeheerPage() {
  const [tab, setTab] = useState<(typeof tabs)[number]["key"]>("users");

  return (
    <>
      <Topbar title="Beheer" breadcrumbs={["Systeem"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <div className="flex items-center gap-1 border-b border-border">
          {tabs.map((t) => {
            const Icon = t.icon;
            const active = tab === t.key;
            return (
              <button
                key={t.key}
                onClick={() => setTab(t.key)}
                className={cn(
                  "flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors",
                  active ? "border-primary text-foreground" : "border-transparent text-muted-foreground hover:text-foreground",
                )}
              >
                <Icon className="h-3.5 w-3.5" /> {t.label}
              </button>
            );
          })}
        </div>

        {tab === "users" && <UsersTab />}
        {tab === "api" && <ApiTab />}
        {tab === "mail" && <MailTab />}
        {tab === "logs" && <LogsTab />}
      </div>
    </>
  );
}

function UsersTab() {
  return (
    <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <Card className="p-0 overflow-hidden">
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <h3 className="text-sm font-semibold">Gebruikers</h3>
          <Button size="sm" className="h-8 gap-1.5"><Plus className="h-3.5 w-3.5" /> Nieuwe gebruiker</Button>
        </div>
        <table className="w-full text-sm">
          <thead>
            <tr className="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-border bg-muted/40">
              <th className="text-left font-semibold px-4 py-2.5">Naam</th>
              <th className="text-left font-semibold px-2 py-2.5">Rollen</th>
              <th className="text-center font-semibold px-2 py-2.5 w-12">MFA</th>
              <th className="text-right font-semibold px-4 py-2.5 w-28">Laatst</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {beheerUsers.map((u) => (
              <tr key={u.email} className="hover:bg-muted/40">
                <td className="px-4 py-3">
                  <div className="text-sm font-medium">{u.naam}</div>
                  <div className="font-mono text-[10.5px] text-muted-foreground">{u.email}</div>
                </td>
                <td className="px-2 py-3">
                  <div className="flex flex-wrap gap-1">
                    {u.rollen.map((r) => (
                      <span key={r} className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-muted text-foreground">{r}</span>
                    ))}
                  </div>
                </td>
                <td className="px-2 py-3 text-center">
                  {u.mfa ? <ShieldCheck className="h-4 w-4 text-status-opgelost inline" /> : <ShieldAlert className="h-4 w-4 text-destructive inline" />}
                </td>
                <td className="px-4 py-3 text-right text-[11px] text-muted-foreground">{u.laatstIngelogd}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Card className="p-0 overflow-hidden">
        <div className="px-4 py-3 border-b border-border">
          <h3 className="text-sm font-semibold">Rollenmatrix</h3>
          <p className="text-[11px] text-muted-foreground">Welke rol mag wat</p>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="text-[10px] uppercase tracking-wider text-muted-foreground border-b border-border bg-muted/40">
                <th className="text-left font-semibold px-3 py-2 sticky left-0 bg-muted/40 z-10">Recht</th>
                {rollen.map((r) => (
                  <th key={r} className="font-semibold px-2 py-2 text-center whitespace-nowrap">{r.slice(0, 8)}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {rechten.map((r) => (
                <tr key={r.naam} className="hover:bg-muted/40">
                  <td className="px-3 py-2 font-medium sticky left-0 bg-card whitespace-nowrap">{r.naam}</td>
                  {r.matrix.map((v, i) => (
                    <td key={i} className="px-2 py-2 text-center">
                      {v ? <CheckCircle2 className="h-3.5 w-3.5 text-primary inline" /> : <span className="text-muted-foreground/40">·</span>}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

function ApiTab() {
  return (
    <Card className="p-0 overflow-hidden">
      <div className="flex items-center justify-between px-4 py-3 border-b border-border">
        <h3 className="text-sm font-semibold">API-sleutels</h3>
        <Button size="sm" className="h-8 gap-1.5"><Plus className="h-3.5 w-3.5" /> Nieuwe sleutel</Button>
      </div>
      <table className="w-full text-sm">
        <thead>
          <tr className="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-border bg-muted/40">
            <th className="text-left font-semibold px-4 py-2.5">Naam</th>
            <th className="text-left font-semibold px-2 py-2.5">Sleutel</th>
            <th className="text-left font-semibold px-2 py-2.5">Scope</th>
            <th className="text-left font-semibold px-2 py-2.5 w-32">Gemaakt door</th>
            <th className="text-right font-semibold px-4 py-2.5 w-32">Laatst gebruikt</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {apiKeys.map((k) => (
            <tr key={k.naam} className="hover:bg-muted/40">
              <td className="px-4 py-3">
                <div className="text-sm font-medium">{k.naam}</div>
                <div className="text-[10.5px] text-muted-foreground">gemaakt {k.gemaakt}</div>
              </td>
              <td className="px-2 py-3">
                <div className="flex items-center gap-1.5">
                  <span className="font-mono text-[11px] text-muted-foreground">{k.key}</span>
                  <button className="text-muted-foreground hover:text-foreground"><Copy className="h-3 w-3" /></button>
                </div>
              </td>
              <td className="px-2 py-3">
                <div className="flex flex-wrap gap-1">
                  {k.scope.map((s) => (
                    <span key={s} className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-primary/10 text-primary">{s}</span>
                  ))}
                </div>
              </td>
              <td className="px-2 py-3 text-xs text-muted-foreground">{k.door}</td>
              <td className="px-4 py-3 text-right text-[11px] text-muted-foreground">{k.laatstGebruikt}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </Card>
  );
}

function MailTab() {
  const toneOf = (s: string) => (s === "verzonden" ? "opgelost" : s === "wachtrij" ? "wachtend" : "gesloten");
  return (
    <Card className="p-0 overflow-hidden">
      <div className="flex items-center justify-between px-4 py-3 border-b border-border">
        <h3 className="text-sm font-semibold">E-mailqueue</h3>
        <div className="text-[11px] text-muted-foreground">
          {mailQueue.filter((m) => m.status === "verzonden").length} verzonden · {mailQueue.filter((m) => m.status === "wachtrij").length} wachtrij · {mailQueue.filter((m) => m.status === "fout").length} fout
        </div>
      </div>
      <table className="w-full text-sm">
        <thead>
          <tr className="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-border bg-muted/40">
            <th className="text-left font-semibold px-4 py-2.5 w-24">ID</th>
            <th className="text-left font-semibold px-2 py-2.5 w-64">Ontvanger</th>
            <th className="text-left font-semibold px-2 py-2.5">Onderwerp</th>
            <th className="text-left font-semibold px-2 py-2.5 w-28">Status</th>
            <th className="text-right font-semibold px-4 py-2.5 w-32">Tijd</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {mailQueue.map((m) => {
            const tone = m.status === "fout" ? "destructive" : toneOf(m.status);
            return (
              <tr key={m.id} className="hover:bg-muted/40">
                <td className="px-4 py-3 font-mono text-xs text-muted-foreground">{m.id}</td>
                <td className="px-2 py-3 font-mono text-xs">{m.ontvanger}</td>
                <td className="px-2 py-3">{m.onderwerp}</td>
                <td className="px-2 py-3">
                  {m.status === "verzonden" && <span className="inline-flex items-center gap-1 text-[11px] text-status-opgelost"><CheckCircle2 className="h-3 w-3" /> Verzonden</span>}
                  {m.status === "wachtrij" && <span className="inline-flex items-center gap-1 text-[11px] text-status-wachtend"><Clock className="h-3 w-3" /> Wachtrij</span>}
                  {m.status === "fout" && <span className="inline-flex items-center gap-1 text-[11px] text-destructive"><XCircle className="h-3 w-3" /> Fout</span>}
                  {tone === "destructive" ? null : null}
                </td>
                <td className="px-4 py-3 text-right text-[11px] text-muted-foreground">{m.tijd}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </Card>
  );
}

function LogsTab() {
  const [q, setQ] = useState("");
  const [niveau, setNiveau] = useState<"alle" | "info" | "warn" | "error">("alle");
  const filtered = systeemLogs.filter((l) => (niveau === "alle" || l.niveau === niveau) && (q === "" || l.bericht.toLowerCase().includes(q.toLowerCase()) || l.bron.includes(q)));

  return (
    <Card className="p-0 overflow-hidden">
      <div className="flex items-center gap-3 px-4 py-3 border-b border-border">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Zoek in logs…" className="pl-9 h-8 text-xs" />
        </div>
        <div className="flex items-center gap-1">
          {(["alle", "info", "warn", "error"] as const).map((n) => (
            <button
              key={n}
              onClick={() => setNiveau(n)}
              className={cn(
                "px-2.5 py-1 rounded text-xs font-medium capitalize",
                niveau === n ? "bg-foreground text-background" : "text-muted-foreground hover:bg-muted",
              )}
            >
              {n}
            </button>
          ))}
        </div>
      </div>
      <div className="p-4 font-mono text-[11.5px] space-y-1 bg-muted/20">
        {filtered.map((l, i) => (
          <div key={i} className="flex gap-3">
            <span className="text-muted-foreground w-20 shrink-0">{l.tijd}</span>
            <span className={cn(
              "w-12 shrink-0 uppercase font-semibold",
              l.niveau === "error" && "text-destructive",
              l.niveau === "warn" && "text-status-wachtend",
              l.niveau === "info" && "text-status-open",
            )}>
              {l.niveau}
            </span>
            <span className="text-muted-foreground w-20 shrink-0">{l.bron}</span>
            <span className="text-foreground/90">{l.bericht}</span>
          </div>
        ))}
      </div>
    </Card>
  );
}
