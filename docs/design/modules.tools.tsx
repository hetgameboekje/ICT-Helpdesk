import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { devices, deviceStatusLabels, type DeviceStatus } from "@/lib/mock-data";
import { Laptop, Search, Plus, Wifi, WifiOff, MapPin, UserCircle2 } from "lucide-react";
import { useMemo, useState } from "react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/modules/device")({
  head: () => ({
    meta: [
      { title: "Devices — Leen van Punt Intranet" },
      { name: "description", content: "Registratie van laptops, telefoons en andere endpoints." },
      { property: "og:title", content: "Devices — Leen van Punt Intranet" },
      { property: "og:description", content: "Registratie van laptops, telefoons en andere endpoints." },
    ],
  }),
  component: DevicesPage,
});

const statusTone: Record<DeviceStatus, string> = {
  "in-gebruik": "behandeling",
  voorraad: "opgelost",
  reparatie: "wachtend",
  afgeschreven: "gesloten",
};

function DevicesPage() {
  const [q, setQ] = useState("");
  const [selId, setSelId] = useState(devices[0].id);
  const filtered = useMemo(() => devices.filter((d) => q === "" || d.hostname.toLowerCase().includes(q.toLowerCase()) || (d.medewerker ?? "").toLowerCase().includes(q.toLowerCase())), [q]);
  const sel = devices.find((d) => d.id === selId) ?? filtered[0];

  const kpis = [
    { label: "In gebruik", value: devices.filter((d) => d.status === "in-gebruik").length, tone: "behandeling" },
    { label: "Online nu", value: devices.filter((d) => d.online).length, tone: "opgelost" },
    { label: "In voorraad", value: devices.filter((d) => d.status === "voorraad").length, tone: "open" },
    { label: "Reparatie / afgeschreven", value: devices.filter((d) => d.status === "reparatie" || d.status === "afgeschreven").length, tone: "wachtend" },
  ];

  return (
    <>
      <Topbar title="Devices" breadcrumbs={["Assets & Beheer"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((k) => (
            <Card key={k.label} className="p-4 gap-2">
              <div className="flex items-start justify-between">
                <div className="text-xs font-medium text-muted-foreground">{k.label}</div>
                <div className="grid place-items-center h-7 w-7 rounded-md" style={{ backgroundColor: `var(--status-${k.tone}-bg)`, color: `var(--status-${k.tone})` }}>
                  <Laptop className="h-3.5 w-3.5" />
                </div>
              </div>
              <div className="text-3xl font-semibold tracking-tight tabular-nums">{k.value}</div>
            </Card>
          ))}
        </section>

        <div className="flex gap-3">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Zoek op hostname of medewerker…" className="pl-9 h-9" />
          </div>
          <Button size="sm" className="h-9 gap-1.5"><Plus className="h-3.5 w-3.5" /> Registreer device</Button>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_360px] gap-4">
          <Card className="p-0 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-border bg-muted/40">
                    <th className="text-left font-semibold px-4 py-2.5">Hostname</th>
                    <th className="text-left font-semibold px-2 py-2.5">Type</th>
                    <th className="text-left font-semibold px-2 py-2.5 w-40">Medewerker</th>
                    <th className="text-left font-semibold px-2 py-2.5 w-40">Locatie</th>
                    <th className="text-left font-semibold px-2 py-2.5 w-32">Status</th>
                    <th className="text-right font-semibold px-4 py-2.5 w-32">Laatst gezien</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {filtered.map((d) => (
                    <tr key={d.id} onClick={() => setSelId(d.id)} className={cn("cursor-pointer hover:bg-muted/40", sel?.id === d.id && "bg-muted/60")}>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          {d.online ? <Wifi className="h-3.5 w-3.5 text-status-opgelost" /> : <WifiOff className="h-3.5 w-3.5 text-muted-foreground/50" />}
                          <span className="font-mono text-xs font-medium">{d.hostname}</span>
                        </div>
                        <div className="text-[10.5px] text-muted-foreground mt-0.5 font-mono">{d.serie}</div>
                      </td>
                      <td className="px-2 py-3 text-xs">{d.type}</td>
                      <td className="px-2 py-3 text-xs">{d.medewerker ?? <span className="text-muted-foreground italic">—</span>}</td>
                      <td className="px-2 py-3 text-xs text-muted-foreground">{d.locatie}</td>
                      <td className="px-2 py-3">
                        <span
                          className="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset"
                          style={{ backgroundColor: `var(--status-${statusTone[d.status]}-bg)`, color: `var(--status-${statusTone[d.status]})` }}
                        >
                          <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: `var(--status-${statusTone[d.status]})` }} />
                          {deviceStatusLabels[d.status]}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right text-[11px] text-muted-foreground">{d.laatstGezien}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>

          {sel && (
            <Card className="p-5 space-y-4">
              <div>
                <div className="flex items-center gap-2">
                  {sel.online ? <Wifi className="h-4 w-4 text-status-opgelost" /> : <WifiOff className="h-4 w-4 text-muted-foreground/60" />}
                  <span className="font-mono text-sm font-semibold">{sel.hostname}</span>
                </div>
                <div className="text-xs text-muted-foreground mt-1">{sel.type} · {sel.os}</div>
              </div>
              <dl className="space-y-2.5 text-sm">
                <Row icon={UserCircle2} label="Toegewezen aan" value={sel.medewerker ?? "—"} />
                <Row icon={MapPin} label="Locatie" value={sel.locatie} />
                <Row icon={Laptop} label="Serie" value={sel.serie} mono />
                <Row icon={Laptop} label="Ingezet sinds" value={sel.ingezet} />
              </dl>
              <div className="grid grid-cols-2 gap-2">
                <Button size="sm" variant="outline">Herstart</Button>
                <Button size="sm" variant="outline">Wipe</Button>
              </div>
            </Card>
          )}
        </div>
      </div>
    </>
  );
}

function Row({ icon: Icon, label, value, mono }: { icon: React.ComponentType<{ className?: string }>; label: string; value: string; mono?: boolean }) {
  return (
    <div className="flex items-start gap-2.5">
      <Icon className="h-3.5 w-3.5 mt-0.5 text-muted-foreground" />
      <div className="min-w-0 flex-1">
        <div className="text-[11px] text-muted-foreground">{label}</div>
        <div className={cn("font-medium truncate", mono && "font-mono text-xs")}>{value}</div>
      </div>
    </div>
  );
}
