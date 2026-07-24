import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { scripts } from "@/lib/mock-data";
import { Code2, Play, CheckCircle2, XCircle, Clock, Terminal } from "lucide-react";
import { useState } from "react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/modules/script")({
  head: () => ({
    meta: [
      { title: "Scripts — Leen van Punt Intranet" },
      { name: "description", content: "Interne scripts met laatst-uitgevoerd status en terminal-preview." },
      { property: "og:title", content: "Scripts — Leen van Punt Intranet" },
      { property: "og:description", content: "Interne scripts met status en terminal-preview." },
    ],
  }),
  component: ScriptsPage,
});

const taalKleur: Record<string, string> = {
  PowerShell: "bg-status-open-bg text-status-open",
  Bash: "bg-status-behandeling-bg text-status-behandeling",
  Python: "bg-status-opgelost-bg text-status-opgelost",
  SQL: "bg-status-wachtend-bg text-status-wachtend",
};

function ScriptsPage() {
  const [selId, setSelId] = useState(scripts[0].id);
  const sel = scripts.find((s) => s.id === selId)!;

  const kpis = [
    { label: "Scripts", value: scripts.length, tone: "open" },
    { label: "Ok laatste run", value: scripts.filter((s) => s.status === "ok").length, tone: "opgelost" },
    { label: "Met fout", value: scripts.filter((s) => s.status === "fout").length, tone: "wachtend" },
    { label: "Nooit uitgevoerd", value: scripts.filter((s) => s.status === "nooit").length, tone: "gesloten" },
  ];

  return (
    <>
      <Topbar title="Scripts" breadcrumbs={["Systeem"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((k) => (
            <Card key={k.label} className="p-4 gap-2">
              <div className="flex items-start justify-between">
                <div className="text-xs font-medium text-muted-foreground">{k.label}</div>
                <div className="grid place-items-center h-7 w-7 rounded-md" style={{ backgroundColor: `var(--status-${k.tone}-bg)`, color: `var(--status-${k.tone})` }}>
                  <Code2 className="h-3.5 w-3.5" />
                </div>
              </div>
              <div className="text-3xl font-semibold tracking-tight tabular-nums">{k.value}</div>
            </Card>
          ))}
        </section>

        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,380px)_1fr] gap-4">
          <Card className="p-0 overflow-hidden">
            <div className="divide-y divide-border">
              {scripts.map((s) => (
                <button
                  key={s.id}
                  onClick={() => setSelId(s.id)}
                  className={cn("w-full text-left px-4 py-3 hover:bg-muted/40", sel.id === s.id && "bg-muted/60")}
                >
                  <div className="flex items-center gap-2">
                    <span className={cn("text-[10px] font-medium px-1.5 py-0.5 rounded", taalKleur[s.taal])}>{s.taal}</span>
                    <StatusIcon status={s.status} />
                    <span className="text-[10px] text-muted-foreground ml-auto font-mono">{s.id}</span>
                  </div>
                  <div className="font-mono text-sm mt-1 font-medium">{s.naam}</div>
                  <div className="text-[11px] text-muted-foreground mt-0.5">{s.eigenaar} · laatst {s.laatstUitgevoerd}</div>
                </button>
              ))}
            </div>
          </Card>

          <Card className="p-0 overflow-hidden">
            <div className="flex items-center justify-between px-5 py-3.5 border-b border-border">
              <div>
                <div className="flex items-center gap-2">
                  <span className="font-mono text-sm font-semibold">{sel.naam}</span>
                  <span className={cn("text-[10px] font-medium px-1.5 py-0.5 rounded", taalKleur[sel.taal])}>{sel.taal}</span>
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">{sel.beschrijving}</p>
              </div>
              <Button size="sm" className="h-8 gap-1.5"><Play className="h-3.5 w-3.5" /> Uitvoeren</Button>
            </div>
            <div className="flex items-center gap-2 px-5 py-2 border-b border-border bg-muted/30 text-[11px]">
              <Terminal className="h-3 w-3 text-muted-foreground" />
              <span className="text-muted-foreground">Eigenaar</span>
              <span className="font-medium">{sel.eigenaar}</span>
              <span className="text-muted-foreground/40 mx-1">·</span>
              <span className="text-muted-foreground">Laatste run</span>
              <span className="font-medium">{sel.laatstUitgevoerd}</span>
              <span className="ml-auto"><StatusIcon status={sel.status} /></span>
            </div>
            <pre className="bg-[oklch(0.18_0.015_250)] text-[oklch(0.9_0.01_250)] font-mono text-[12px] leading-relaxed p-5 overflow-x-auto">
              <code>{sel.inhoud}</code>
            </pre>
          </Card>
        </div>
      </div>
    </>
  );
}

function StatusIcon({ status }: { status: "ok" | "fout" | "nooit" }) {
  if (status === "ok") return <span className="inline-flex items-center gap-1 text-[11px] text-status-opgelost"><CheckCircle2 className="h-3 w-3" /> ok</span>;
  if (status === "fout") return <span className="inline-flex items-center gap-1 text-[11px] text-destructive"><XCircle className="h-3 w-3" /> fout</span>;
  return <span className="inline-flex items-center gap-1 text-[11px] text-muted-foreground"><Clock className="h-3 w-3" /> nooit</span>;
}
