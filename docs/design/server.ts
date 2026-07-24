import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { schijfVolumes } from "@/lib/mock-data";
import { HardDrive, AlertTriangle, Server, Database } from "lucide-react";

export const Route = createFileRoute("/modules/schijfgebruik")({
  head: () => ({
    meta: [
      { title: "Schijfgebruik — Leen van Punt Intranet" },
      { name: "description", content: "Schijfruimte per server en volume, gesorteerd op meest volle eerst." },
      { property: "og:title", content: "Schijfgebruik — Leen van Punt Intranet" },
      { property: "og:description", content: "Schijfruimte per server en volume, gesorteerd op meest volle eerst." },
    ],
  }),
  component: SchijfgebruikPage,
});

function colorFor(pct: number) {
  if (pct >= 90) return { fg: "var(--stock-low)", bg: "var(--stock-low-bg)" };
  if (pct >= 75) return { fg: "var(--status-wachtend)", bg: "var(--status-wachtend-bg)" };
  return { fg: "var(--stock-ok)", bg: "var(--stock-ok-bg)" };
}

function SchijfgebruikPage() {
  const sorted = [...schijfVolumes]
    .map((v) => ({ ...v, pct: Math.round((v.gebruiktGB / v.totaalGB) * 100) }))
    .sort((a, b) => b.pct - a.pct);

  const totaal = schijfVolumes.reduce((s, v) => s + v.totaalGB, 0);
  const gebruikt = schijfVolumes.reduce((s, v) => s + v.gebruiktGB, 0);
  const kritiek = sorted.filter((v) => v.pct >= 90).length;

  const kpis = [
    { label: "Volumes", value: schijfVolumes.length, tone: "open", icon: HardDrive },
    { label: "Totaal", value: `${(totaal / 1000).toFixed(1)} TB`, tone: "opgelost", icon: Database },
    { label: "Gebruikt", value: `${(gebruikt / 1000).toFixed(1)} TB`, tone: "behandeling", icon: Server },
    { label: "> 90% vol", value: kritiek, tone: "wachtend", icon: AlertTriangle },
  ];

  return (
    <>
      <Topbar title="Schijfgebruik" breadcrumbs={["Systeem"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((k) => {
            const Icon = k.icon;
            return (
              <Card key={k.label} className="p-4 gap-2">
                <div className="flex items-start justify-between">
                  <div className="text-xs font-medium text-muted-foreground">{k.label}</div>
                  <div className="grid place-items-center h-7 w-7 rounded-md" style={{ backgroundColor: `var(--status-${k.tone}-bg)`, color: `var(--status-${k.tone})` }}>
                    <Icon className="h-3.5 w-3.5" />
                  </div>
                </div>
                <div className="text-3xl font-semibold tracking-tight tabular-nums">{k.value}</div>
              </Card>
            );
          })}
        </section>

        <Card className="p-0 overflow-hidden">
          <div className="px-5 py-3.5 border-b border-border">
            <h2 className="text-sm font-semibold">Volumes · meest vol eerst</h2>
            <p className="text-xs text-muted-foreground">Waarden dagelijks bijgewerkt om 06:00 door <span className="font-mono">check_disk_usage.py</span></p>
          </div>
          <div className="divide-y divide-border">
            {sorted.map((v) => {
              const c = colorFor(v.pct);
              const dashArray = 2 * Math.PI * 22;
              const dashOffset = dashArray * (1 - v.pct / 100);
              return (
                <div key={`${v.server}-${v.volume}`} className="flex items-center gap-4 px-5 py-4 hover:bg-muted/40">
                  <div className="relative h-14 w-14 shrink-0">
                    <svg viewBox="0 0 50 50" className="h-14 w-14 -rotate-90">
                      <circle cx="25" cy="25" r="22" fill="none" stroke="var(--muted)" strokeWidth="4" />
                      <circle
                        cx="25" cy="25" r="22" fill="none" stroke={c.fg} strokeWidth="4" strokeLinecap="round"
                        strokeDasharray={dashArray} strokeDashoffset={dashOffset}
                      />
                    </svg>
                    <div className="absolute inset-0 grid place-items-center text-[11px] font-semibold tabular-nums" style={{ color: c.fg }}>
                      {v.pct}%
                    </div>
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="font-mono text-sm font-semibold">{v.server}</span>
                      <span className="text-muted-foreground/40">·</span>
                      <span className="font-mono text-sm">{v.volume}</span>
                      <span className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-muted text-muted-foreground ml-1">{v.type}</span>
                      {v.pct >= 90 && (
                        <span className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-stock-low-bg text-stock-low flex items-center gap-1 ml-auto">
                          <AlertTriangle className="h-2.5 w-2.5" /> kritiek
                        </span>
                      )}
                    </div>
                    <div className="mt-1.5 flex items-center gap-2">
                      <div className="flex-1 h-1.5 rounded-full bg-muted overflow-hidden">
                        <div className="h-full" style={{ width: `${v.pct}%`, backgroundColor: c.fg }} />
                      </div>
                      <span className="text-[11px] tabular-nums text-muted-foreground w-40 text-right">
                        {v.gebruiktGB.toLocaleString("nl-NL")} / {v.totaalGB.toLocaleString("nl-NL")} GB
                      </span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </Card>
      </div>
    </>
  );
}
