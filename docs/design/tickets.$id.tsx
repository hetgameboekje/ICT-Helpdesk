import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { printers } from "@/lib/mock-data";
import { Printer, Wifi, WifiOff, CheckCircle2, AlertTriangle, Droplet } from "lucide-react";
import { useState } from "react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/modules/printer")({
  head: () => ({
    meta: [
      { title: "Printers — Leen van Punt Intranet" },
      { name: "description", content: "Printerbeheer met toner-status en laatste printjobs." },
      { property: "og:title", content: "Printers — Leen van Punt Intranet" },
      { property: "og:description", content: "Printerbeheer met toner-status en laatste printjobs." },
    ],
  }),
  component: PrintersPage,
});

const tonerColor: Record<string, string> = {
  cyaan: "#06b6d4",
  magenta: "#ec4899",
  geel: "#eab308",
  zwart: "#111827",
};

function PrintersPage() {
  const [selId, setSelId] = useState(printers[0].id);
  const sel = printers.find((p) => p.id === selId)!;

  const kpis = [
    { label: "Printers", value: printers.length, tone: "open" },
    { label: "Offline", value: printers.filter((p) => !p.online).length, tone: "wachtend" },
    { label: "Toner < 20%", value: printers.reduce((s, p) => s + p.toner.filter((t) => t.percent < 20).length, 0), tone: "behandeling" },
    { label: "Pagina's totaal", value: printers.reduce((s, p) => s + p.paginas, 0).toLocaleString("nl-NL"), tone: "opgelost" },
  ];

  return (
    <>
      <Topbar title="Printers" breadcrumbs={["Assets & Beheer"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((k) => (
            <Card key={k.label} className="p-4 gap-2">
              <div className="flex items-start justify-between">
                <div className="text-xs font-medium text-muted-foreground">{k.label}</div>
                <div className="grid place-items-center h-7 w-7 rounded-md" style={{ backgroundColor: `var(--status-${k.tone}-bg)`, color: `var(--status-${k.tone})` }}>
                  <Printer className="h-3.5 w-3.5" />
                </div>
              </div>
              <div className="text-3xl font-semibold tracking-tight tabular-nums">{k.value}</div>
            </Card>
          ))}
        </section>

        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,380px)_1fr] gap-4">
          <Card className="p-0 overflow-hidden">
            <div className="divide-y divide-border">
              {printers.map((p) => (
                <button
                  key={p.id}
                  onClick={() => setSelId(p.id)}
                  className={cn("w-full text-left px-4 py-3 hover:bg-muted/40", sel.id === p.id && "bg-muted/60")}
                >
                  <div className="flex items-center gap-2">
                    {p.online ? <Wifi className="h-3.5 w-3.5 text-status-opgelost" /> : <WifiOff className="h-3.5 w-3.5 text-destructive" />}
                    <span className="text-sm font-medium">{p.naam}</span>
                    <span className="text-[11px] text-muted-foreground ml-auto">{p.locatie}</span>
                  </div>
                  <div className="text-[11px] text-muted-foreground mt-0.5">{p.model}</div>
                  <div className="flex gap-1 mt-2">
                    {p.toner.map((t) => (
                      <div key={t.kleur} className="flex-1 h-1 rounded-full bg-muted overflow-hidden">
                        <div className="h-full" style={{ width: `${t.percent}%`, backgroundColor: tonerColor[t.kleur] }} />
                      </div>
                    ))}
                  </div>
                </button>
              ))}
            </div>
          </Card>

          <Card className="p-6 space-y-5">
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="flex items-center gap-2">
                  {sel.online ? <Wifi className="h-4 w-4 text-status-opgelost" /> : <WifiOff className="h-4 w-4 text-destructive" />}
                  <h2 className="text-xl font-semibold tracking-tight">{sel.naam}</h2>
                </div>
                <div className="text-xs text-muted-foreground mt-1">{sel.model} · {sel.locatie}</div>
                <div className="text-[11px] text-muted-foreground mt-0.5">
                  {sel.paginas.toLocaleString("nl-NL")} pagina's totaal · laatste print {sel.laatstePrint}
                </div>
              </div>
              <div className="flex gap-2">
                <Button size="sm" variant="outline">Herstart</Button>
                <Button size="sm">Testpagina</Button>
              </div>
            </div>

            <div>
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Toner</h3>
              <div className="grid grid-cols-4 gap-3">
                {sel.toner.map((t) => {
                  const low = t.percent < 20;
                  return (
                    <div key={t.kleur} className="rounded-lg border border-border p-3">
                      <div className="flex items-center gap-1.5 text-[11px] font-medium capitalize">
                        <Droplet className="h-3 w-3" style={{ color: tonerColor[t.kleur] }} /> {t.kleur}
                      </div>
                      <div className="text-2xl font-semibold tabular-nums mt-1">{t.percent}%</div>
                      <div className="h-1.5 rounded-full bg-muted mt-2 overflow-hidden">
                        <div className="h-full" style={{ width: `${t.percent}%`, backgroundColor: tonerColor[t.kleur] }} />
                      </div>
                      {low && (
                        <div className="mt-1.5 text-[10px] text-destructive font-medium flex items-center gap-1">
                          <AlertTriangle className="h-2.5 w-2.5" /> Bijna leeg
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>

            <div>
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">Recente printjobs</h3>
              {sel.jobs.length === 0 ? (
                <p className="text-sm text-muted-foreground">Geen recente jobs.</p>
              ) : (
                <table className="w-full text-sm">
                  <tbody className="divide-y divide-border">
                    {sel.jobs.map((j, i) => (
                      <tr key={i}>
                        <td className="py-2 text-[11px] text-muted-foreground font-mono w-32">{j.tijd}</td>
                        <td className="py-2">{j.document}</td>
                        <td className="py-2 text-xs text-muted-foreground w-32">{j.gebruiker}</td>
                        <td className="py-2 text-xs text-right tabular-nums w-16">{j.paginas} p</td>
                        <td className="py-2 text-right w-20">
                          {j.status === "ok" ? (
                            <span className="inline-flex items-center gap-1 text-[11px] text-status-opgelost"><CheckCircle2 className="h-3 w-3" /> ok</span>
                          ) : (
                            <span className="inline-flex items-center gap-1 text-[11px] text-destructive"><AlertTriangle className="h-3 w-3" /> fout</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </Card>
        </div>
      </div>
    </>
  );
}
