import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { uitgiften } from "@/lib/mock-data";
import { PackageCheck, Search, Plus, Undo2, Package } from "lucide-react";
import { useMemo, useState } from "react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/modules/hardware-uitgaven")({
  head: () => ({
    meta: [
      { title: "Hardware-uitgaven — Leen van Punt Intranet" },
      { name: "description", content: "Overzicht van hardware die is uitgegeven aan medewerkers, met retourregistratie." },
      { property: "og:title", content: "Hardware-uitgaven — Leen van Punt Intranet" },
      { property: "og:description", content: "Overzicht van uitgegeven hardware met retourregistratie." },
    ],
  }),
  component: HardwareUitgaven,
});

function HardwareUitgaven() {
  const hw = uitgiften.filter((u) => u.soort === "hardware");
  const [q, setQ] = useState("");
  const [selId, setSelId] = useState(hw[0].id);
  const filtered = useMemo(() => hw.filter((u) => q === "" || u.item.toLowerCase().includes(q.toLowerCase()) || u.medewerker.toLowerCase().includes(q.toLowerCase())), [q, hw]);
  const sel = hw.find((u) => u.id === selId) ?? filtered[0];

  const kpis = [
    { label: "Uitgiften", value: hw.length, tone: "open" },
    { label: "Openstaand", value: hw.filter((u) => !u.geretourneerd).length, tone: "behandeling" },
    { label: "Geretourneerd", value: hw.filter((u) => u.geretourneerd).length, tone: "opgelost" },
    { label: "Vandaag", value: hw.filter((u) => u.datum === "22 jul").length, tone: "wachtend" },
  ];

  return (
    <>
      <Topbar title="Hardware-uitgaven" breadcrumbs={["Assets & Beheer"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((k) => (
            <Card key={k.label} className="p-4 gap-2">
              <div className="flex items-start justify-between">
                <div className="text-xs font-medium text-muted-foreground">{k.label}</div>
                <div className="grid place-items-center h-7 w-7 rounded-md" style={{ backgroundColor: `var(--status-${k.tone}-bg)`, color: `var(--status-${k.tone})` }}>
                  <PackageCheck className="h-3.5 w-3.5" />
                </div>
              </div>
              <div className="text-3xl font-semibold tracking-tight tabular-nums">{k.value}</div>
            </Card>
          ))}
        </section>

        <div className="flex gap-3">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Zoek op medewerker of item…" className="pl-9 h-9" />
          </div>
          <Button size="sm" className="h-9 gap-1.5"><Plus className="h-3.5 w-3.5" /> Nieuwe uitgifte</Button>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-4">
          <Card className="p-0 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-border bg-muted/40">
                    <th className="text-left font-semibold px-4 py-2.5 w-20">Nummer</th>
                    <th className="text-left font-semibold px-2 py-2.5 w-24">Datum</th>
                    <th className="text-left font-semibold px-2 py-2.5">Medewerker</th>
                    <th className="text-left font-semibold px-2 py-2.5">Item</th>
                    <th className="text-left font-semibold px-2 py-2.5 w-32">Serienummer</th>
                    <th className="text-right font-semibold px-4 py-2.5 w-32">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {filtered.map((u) => (
                    <tr key={u.id} onClick={() => setSelId(u.id)} className={cn("cursor-pointer hover:bg-muted/40", sel?.id === u.id && "bg-muted/60")}>
                      <td className="px-4 py-3 font-mono text-xs text-muted-foreground">{u.id}</td>
                      <td className="px-2 py-3 text-xs text-muted-foreground">{u.datum}</td>
                      <td className="px-2 py-3">
                        <div className="text-sm font-medium">{u.medewerker}</div>
                        <div className="text-[11px] text-muted-foreground">{u.afdeling}</div>
                      </td>
                      <td className="px-2 py-3 text-sm">{u.item}</td>
                      <td className="px-2 py-3 font-mono text-[11px] text-muted-foreground">{u.serienr}</td>
                      <td className="px-4 py-3 text-right">
                        {u.geretourneerd ? (
                          <span className="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full ring-1 ring-inset bg-status-opgelost-bg text-status-opgelost ring-status-opgelost/20">
                            <Undo2 className="h-2.5 w-2.5" /> retour {u.retourDatum}
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full ring-1 ring-inset bg-status-behandeling-bg text-status-behandeling ring-status-behandeling/20">
                            <span className="h-1.5 w-1.5 rounded-full bg-status-behandeling" /> in gebruik
                          </span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>

          {sel && (
            <Card className="p-5 space-y-4">
              <div>
                <div className="text-[11px] text-muted-foreground font-mono">{sel.id} · {sel.datum}</div>
                <div className="text-base font-semibold mt-1">{sel.item}</div>
                <div className="text-[11px] font-mono text-muted-foreground mt-0.5">{sel.serienr}</div>
              </div>
              <dl className="space-y-2.5 text-sm">
                <div><dt className="text-[11px] text-muted-foreground">Uitgegeven aan</dt><dd className="font-medium">{sel.medewerker}</dd><dd className="text-[11px] text-muted-foreground">{sel.afdeling}</dd></div>
                <div><dt className="text-[11px] text-muted-foreground">Uitgegeven door</dt><dd className="font-medium">{sel.uitgegevenDoor}</dd></div>
              </dl>
              <div className="flex items-center justify-between border-t border-border pt-3">
                <div className="text-xs text-muted-foreground flex items-center gap-2">
                  <Package className="h-3.5 w-3.5" /> Geretourneerd
                </div>
                <button
                  type="button"
                  className={cn("h-5 w-9 rounded-full transition-colors relative", sel.geretourneerd ? "bg-primary" : "bg-muted")}
                >
                  <span className={cn("absolute top-0.5 h-4 w-4 rounded-full bg-white transition-all shadow", sel.geretourneerd ? "left-[18px]" : "left-0.5")} />
                </button>
              </div>
              <Button size="sm" variant="outline" className="w-full">Print uitgiftebon</Button>
            </Card>
          )}
        </div>
      </div>
    </>
  );
}
