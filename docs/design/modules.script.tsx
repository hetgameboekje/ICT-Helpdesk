import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { risicos, risicoLabels, type Risico } from "@/lib/mock-data";
import { ShieldAlert, Search, Plus, ShieldCheck, ShieldOff } from "lucide-react";
import { useMemo, useState } from "react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/modules/cyberrisico")({
  head: () => ({
    meta: [
      { title: "CyberRisico — Leen van Punt Intranet" },
      { name: "description", content: "Register van cyberrisico's met risicomatrix (kans × impact) en eigen kleurschaal." },
      { property: "og:title", content: "CyberRisico — Leen van Punt Intranet" },
      { property: "og:description", content: "Register van cyberrisico's met risicomatrix en eigen kleurschaal." },
    ],
  }),
  component: CyberRisicoPage,
});

function levelFrom(kans: number, impact: number): Risico {
  const s = kans * impact;
  if (s >= 20) return "kritiek";
  if (s >= 12) return "hoog";
  if (s >= 6) return "gemiddeld";
  return "laag";
}

function CyberRisicoPage() {
  const [q, setQ] = useState("");
  const [selId, setSelId] = useState(risicos[0].id);
  const filtered = useMemo(() => risicos.filter((r) => q === "" || r.titel.toLowerCase().includes(q.toLowerCase())), [q]);
  const sel = risicos.find((r) => r.id === selId) ?? filtered[0];

  const kpis: { label: string; value: number; niv: Risico }[] = [
    { label: "Kritiek", value: risicos.filter((r) => r.niveau === "kritiek").length, niv: "kritiek" },
    { label: "Hoog", value: risicos.filter((r) => r.niveau === "hoog").length, niv: "hoog" },
    { label: "Gemiddeld", value: risicos.filter((r) => r.niveau === "gemiddeld").length, niv: "gemiddeld" },
    { label: "Laag", value: risicos.filter((r) => r.niveau === "laag").length, niv: "laag" },
  ];

  return (
    <>
      <Topbar title="CyberRisico" breadcrumbs={["Assets & Beheer"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((k) => (
            <Card key={k.label} className="p-4 gap-2">
              <div className="flex items-start justify-between">
                <div className="text-xs font-medium text-muted-foreground">{k.label}</div>
                <div className="grid place-items-center h-7 w-7 rounded-md" style={{ backgroundColor: `var(--risk-${k.niv}-bg)`, color: `var(--risk-${k.niv})` }}>
                  {k.niv === "kritiek" ? <ShieldOff className="h-3.5 w-3.5" /> : k.niv === "laag" ? <ShieldCheck className="h-3.5 w-3.5" /> : <ShieldAlert className="h-3.5 w-3.5" />}
                </div>
              </div>
              <div className="text-3xl font-semibold tracking-tight tabular-nums">{k.value}</div>
            </Card>
          ))}
        </section>

        <div className="flex gap-3">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Zoek risico…" className="pl-9 h-9" />
          </div>
          <Button size="sm" className="h-9 gap-1.5"><Plus className="h-3.5 w-3.5" /> Registreer risico</Button>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_380px] gap-4">
          <Card className="p-0 overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-border bg-muted/40">
                  <th className="text-left font-semibold px-4 py-2.5 w-20">Nummer</th>
                  <th className="text-left font-semibold px-2 py-2.5">Risico</th>
                  <th className="text-left font-semibold px-2 py-2.5 w-32">Eigenaar</th>
                  <th className="text-center font-semibold px-2 py-2.5 w-14">Kans</th>
                  <th className="text-center font-semibold px-2 py-2.5 w-14">Impact</th>
                  <th className="text-left font-semibold px-2 py-2.5 w-28">Niveau</th>
                  <th className="text-right font-semibold px-4 py-2.5 w-24">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {filtered.map((r) => (
                  <tr key={r.id} onClick={() => setSelId(r.id)} className={cn("cursor-pointer hover:bg-muted/40", sel?.id === r.id && "bg-muted/60")}>
                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">{r.id}</td>
                    <td className="px-2 py-3">
                      <div className="text-sm font-medium line-clamp-1">{r.titel}</div>
                      <div className="text-[11px] text-muted-foreground mt-0.5">{r.categorie}</div>
                    </td>
                    <td className="px-2 py-3 text-xs">{r.eigenaar}</td>
                    <td className="px-2 py-3 text-center font-mono text-xs">{r.kans}</td>
                    <td className="px-2 py-3 text-center font-mono text-xs">{r.impact}</td>
                    <td className="px-2 py-3"><RiskBadge niveau={r.niveau} /></td>
                    <td className="px-4 py-3 text-right text-[11px] text-muted-foreground capitalize">{r.status}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>

          {sel && (
            <div className="space-y-4">
              <Card className="p-5 space-y-3">
                <div className="flex items-center gap-2">
                  <span className="font-mono text-[11px] text-muted-foreground">{sel.id}</span>
                  <RiskBadge niveau={sel.niveau} />
                </div>
                <h2 className="text-lg font-semibold tracking-tight leading-snug">{sel.titel}</h2>
                <p className="text-sm text-muted-foreground leading-relaxed">{sel.beschrijving}</p>
                <div className="grid grid-cols-2 gap-3 text-sm pt-2 border-t border-border">
                  <div><div className="text-[11px] text-muted-foreground">Eigenaar</div><div className="font-medium">{sel.eigenaar}</div></div>
                  <div><div className="text-[11px] text-muted-foreground">Categorie</div><div className="font-medium">{sel.categorie}</div></div>
                </div>
              </Card>

              <Card className="p-5">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Risicomatrix</h3>
                <div className="grid grid-cols-[auto_repeat(5,1fr)] gap-1 text-[10px]">
                  <div />
                  {[1, 2, 3, 4, 5].map((k) => (
                    <div key={k} className="text-center text-muted-foreground pb-1">K{k}</div>
                  ))}
                  {[5, 4, 3, 2, 1].map((impact) => (
                    <div key={`row-${impact}`} className="contents">
                      <div className="text-muted-foreground pr-1 text-right leading-6">I{impact}</div>
                      {[1, 2, 3, 4, 5].map((kans) => {
                        const niv = levelFrom(kans, impact);
                        const active = sel.kans === kans && sel.impact === impact;
                        return (
                          <div
                            key={`c-${impact}-${kans}`}
                            className={cn("aspect-square rounded flex items-center justify-center font-medium", active && "ring-2 ring-foreground")}
                            style={{ backgroundColor: `var(--risk-${niv}-bg)`, color: `var(--risk-${niv})` }}
                          >
                            {active ? "●" : ""}
                          </div>
                        );
                      })}
                    </div>
                  ))}
                </div>
                <div className="mt-3 text-[11px] text-muted-foreground">
                  Kans <span className="font-mono text-foreground">{sel.kans}</span> × Impact <span className="font-mono text-foreground">{sel.impact}</span> = score <span className="font-mono text-foreground">{sel.kans * sel.impact}</span>
                </div>
              </Card>
            </div>
          )}
        </div>
      </div>
    </>
  );
}

export function RiskBadge({ niveau }: { niveau: Risico }) {
  return (
    <span
      className="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
      style={{ backgroundColor: `var(--risk-${niveau}-bg)`, color: `var(--risk-${niveau})`, boxShadow: `inset 0 0 0 1px var(--risk-${niveau})30` }}
    >
      <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: `var(--risk-${niveau})` }} />
      {risicoLabels[niveau]}
    </span>
  );
}
