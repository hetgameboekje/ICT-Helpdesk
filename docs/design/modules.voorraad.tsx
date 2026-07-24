import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { medewerkers } from "@/lib/mock-data";
import { Users, Search, Plus, Mail, Phone, Key, ChevronRight, Sparkles } from "lucide-react";
import { useMemo, useState } from "react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/modules/medewerker")({
  head: () => ({
    meta: [
      { title: "Medewerkers — Leen van Punt Intranet" },
      { name: "description", content: "Interne CRM met hiërarchie, keyusers en team-in-behandeling widget." },
      { property: "og:title", content: "Medewerkers — Leen van Punt Intranet" },
      { property: "og:description", content: "Interne CRM met hiërarchie, keyusers en team-in-behandeling widget." },
    ],
  }),
  component: MedewerkersPage,
});

function MedewerkersPage() {
  const [q, setQ] = useState("");
  const [selId, setSelId] = useState(medewerkers[1].id);
  const filtered = useMemo(() => medewerkers.filter((m) => q === "" || m.naam.toLowerCase().includes(q.toLowerCase()) || m.afdeling.toLowerCase().includes(q.toLowerCase())), [q]);
  const sel = medewerkers.find((m) => m.id === selId)!;
  const manager = medewerkers.find((m) => m.naam === sel.manager);
  const teamleden = medewerkers.filter((m) => m.manager === sel.naam);

  const kpis = [
    { label: "Medewerkers", value: medewerkers.length, tone: "open" },
    { label: "Keyusers", value: medewerkers.filter((m) => m.keyuser).length, tone: "behandeling" },
    { label: "Nu actief", value: medewerkers.filter((m) => m.status === "actief").length, tone: "opgelost" },
    { label: "Op verlof", value: medewerkers.filter((m) => m.status === "verlof").length, tone: "wachtend" },
  ];

  return (
    <>
      <Topbar title="Medewerkers" breadcrumbs={["HR & CRM"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((k) => (
            <Card key={k.label} className="p-4 gap-2">
              <div className="flex items-start justify-between">
                <div className="text-xs font-medium text-muted-foreground">{k.label}</div>
                <div className="grid place-items-center h-7 w-7 rounded-md" style={{ backgroundColor: `var(--status-${k.tone}-bg)`, color: `var(--status-${k.tone})` }}>
                  <Users className="h-3.5 w-3.5" />
                </div>
              </div>
              <div className="text-3xl font-semibold tracking-tight tabular-nums">{k.value}</div>
            </Card>
          ))}
        </section>

        <div className="flex gap-3">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Zoek op naam of afdeling…" className="pl-9 h-9" />
          </div>
          <Button size="sm" className="h-9 gap-1.5"><Plus className="h-3.5 w-3.5" /> Nieuwe medewerker</Button>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_420px] gap-4">
          <Card className="p-4">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              {filtered.map((m) => (
                <button
                  key={m.id}
                  onClick={() => setSelId(m.id)}
                  className={cn(
                    "text-left rounded-lg border p-3 hover:border-primary/40 hover:shadow-sm transition-all",
                    sel.id === m.id ? "border-primary bg-primary/5" : "border-border bg-card",
                  )}
                >
                  <div className="flex items-center gap-3">
                    <div className="grid place-items-center h-9 w-9 rounded-full bg-muted text-xs font-semibold">
                      {m.initialen}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="text-sm font-medium flex items-center gap-1 truncate">
                        {m.naam}
                        {m.keyuser && <Key className="h-3 w-3 text-primary shrink-0" />}
                      </div>
                      <div className="text-[11px] text-muted-foreground truncate">{m.rol}</div>
                    </div>
                    {m.status === "verlof" && <span className="h-1.5 w-1.5 rounded-full bg-status-wachtend" title="Op verlof" />}
                    {m.status === "actief" && <span className="h-1.5 w-1.5 rounded-full bg-status-opgelost" title="Actief" />}
                  </div>
                  <div className="mt-2 flex items-center justify-between text-[10.5px] text-muted-foreground">
                    <span>{m.afdeling} · {m.team}</span>
                    {m.inBehandeling > 0 && (
                      <span className="font-mono px-1.5 rounded bg-status-behandeling-bg text-status-behandeling">
                        {m.inBehandeling} open
                      </span>
                    )}
                  </div>
                </button>
              ))}
            </div>
          </Card>

          <div className="space-y-4">
            <Card className="p-5">
              <div className="flex items-center gap-3">
                <div className="grid place-items-center h-12 w-12 rounded-full bg-primary/10 text-primary text-sm font-semibold">
                  {sel.initialen}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="text-base font-semibold flex items-center gap-1.5">
                    {sel.naam}
                    {sel.keyuser && (
                      <span className="text-[10px] font-medium px-1.5 py-0.5 rounded bg-primary/15 text-primary flex items-center gap-0.5">
                        <Key className="h-2.5 w-2.5" /> keyuser
                      </span>
                    )}
                  </div>
                  <div className="text-xs text-muted-foreground">{sel.rol}</div>
                </div>
              </div>
              <dl className="mt-4 space-y-2 text-sm">
                <div className="flex items-center gap-2"><Mail className="h-3.5 w-3.5 text-muted-foreground" /> <span className="font-mono text-xs">{sel.email}</span></div>
                <div className="flex items-center gap-2"><Phone className="h-3.5 w-3.5 text-muted-foreground" /> <span className="font-mono text-xs">{sel.telefoon}</span> · toestel {sel.toestel}</div>
              </dl>
            </Card>

            <Card className="p-5">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Hiërarchie</h3>
              <div className="space-y-2">
                {manager && (
                  <div className="flex items-center gap-2 text-sm">
                    <div className="grid place-items-center h-7 w-7 rounded-full bg-muted text-[10px] font-semibold">{manager.initialen}</div>
                    <div><div className="font-medium">{manager.naam}</div><div className="text-[11px] text-muted-foreground">Manager</div></div>
                  </div>
                )}
                <div className="pl-3 border-l-2 border-primary/30 ml-3 py-1">
                  <div className="flex items-center gap-2 text-sm">
                    <div className="grid place-items-center h-7 w-7 rounded-full bg-primary/15 text-primary text-[10px] font-semibold">{sel.initialen}</div>
                    <div><div className="font-medium">{sel.naam}</div><div className="text-[11px] text-muted-foreground">{sel.rol}</div></div>
                  </div>
                </div>
                {teamleden.length > 0 && (
                  <div className="pl-6 border-l-2 border-border ml-3 space-y-1.5 pt-1">
                    <div className="text-[10.5px] uppercase tracking-wider text-muted-foreground">Team ({teamleden.length})</div>
                    {teamleden.map((t) => (
                      <div key={t.id} className="flex items-center gap-2 text-xs">
                        <div className="grid place-items-center h-5 w-5 rounded-full bg-muted text-[9px] font-semibold">{t.initialen}</div>
                        {t.naam} <span className="text-muted-foreground">— {t.rol}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </Card>

            {sel.inBehandeling > 0 && (
              <Card className="p-5 bg-gradient-to-br from-status-behandeling-bg/60 to-transparent border-status-behandeling/20">
                <div className="flex items-center gap-2 mb-2">
                  <Sparkles className="h-3.5 w-3.5 text-status-behandeling" />
                  <h3 className="text-xs font-semibold uppercase tracking-wider text-status-behandeling">
                    In behandeling nu
                  </h3>
                </div>
                <div className="text-2xl font-semibold tabular-nums">{sel.inBehandeling} tickets</div>
                <p className="text-xs text-muted-foreground mt-1">
                  Zichtbaar in Agenda-module als "in behandeling"-blokken.
                </p>
                <Button size="sm" variant="outline" className="mt-3 h-8 text-xs gap-1">
                  Bekijk in agenda <ChevronRight className="h-3 w-3" />
                </Button>
              </Card>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
