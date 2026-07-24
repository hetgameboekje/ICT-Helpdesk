import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { agendaEvents, medewerkers } from "@/lib/mock-data";
import { CalendarDays, ChevronLeft, ChevronRight, Users } from "lucide-react";

export const Route = createFileRoute("/modules/agenda")({
  head: () => ({
    meta: [
      { title: "Agenda — Leen van Punt Intranet" },
      { name: "description", content: "Teamkalender die laat zien wie waarmee bezig is, gekoppeld aan 'in behandeling'-tickets." },
      { property: "og:title", content: "Agenda — Leen van Punt Intranet" },
      { property: "og:description", content: "Teamkalender met 'in behandeling'-blokken uit tickets." },
    ],
  }),
  component: AgendaPage,
});

const dagen = ["Ma 21 jul", "Di 22 jul", "Wo 23 jul", "Do 24 jul", "Vr 25 jul"];
const uren = Array.from({ length: 10 }, (_, i) => 8 + i); // 8-17

function AgendaPage() {
  const teamLeden = medewerkers.filter((m) => ["Support", "Infra"].includes(m.team));

  return (
    <>
      <Topbar title="Agenda" breadcrumbs={["Werkplek"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <div className="flex items-center gap-3">
          <div className="flex items-center gap-1">
            <Button variant="outline" size="icon" className="h-8 w-8"><ChevronLeft className="h-4 w-4" /></Button>
            <Button variant="outline" size="sm" className="h-8 text-xs">Vandaag</Button>
            <Button variant="outline" size="icon" className="h-8 w-8"><ChevronRight className="h-4 w-4" /></Button>
          </div>
          <div className="text-sm font-semibold">Week 30 · 21 – 25 juli 2026</div>
          <div className="flex-1" />
          <div className="flex items-center gap-1">
            {["Dag", "Week", "Team"].map((v, i) => (
              <button key={v} className={`px-3 py-1.5 rounded-md text-xs font-medium ${i === 1 ? "bg-foreground text-background" : "text-muted-foreground hover:bg-muted"}`}>
                {v}
              </button>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_300px] gap-4">
          <Card className="p-0 overflow-hidden">
            <div className="overflow-x-auto">
              <div className="min-w-[900px]">
                <div className="grid grid-cols-[64px_repeat(5,1fr)] border-b border-border bg-muted/40">
                  <div />
                  {dagen.map((d) => (
                    <div key={d} className="px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground border-l border-border">
                      {d}
                    </div>
                  ))}
                </div>
                <div className="grid grid-cols-[64px_repeat(5,1fr)] relative">
                  <div className="border-r border-border">
                    {uren.map((u) => (
                      <div key={u} className="h-14 px-2 text-[10px] font-mono text-muted-foreground pt-1">{u}:00</div>
                    ))}
                  </div>
                  {dagen.map((_, di) => (
                    <div key={di} className="border-r border-border relative">
                      {uren.map((u) => (
                        <div key={u} className="h-14 border-b border-border/60" />
                      ))}
                      {agendaEvents.filter((e) => e.dag === di).map((e, ei) => {
                        const top = (e.startUur - 8) * 56;
                        const height = e.duur * 56 - 4;
                        return (
                          <div
                            key={ei}
                            className="absolute left-1 right-1 rounded-md px-2 py-1.5 text-[11px] leading-tight ring-1 ring-inset overflow-hidden"
                            style={{
                              top,
                              height,
                              backgroundColor: `var(--status-${e.tone}-bg)`,
                              color: `var(--status-${e.tone})`,
                              boxShadow: `inset 0 0 0 1px var(--status-${e.tone})30`,
                            }}
                          >
                            <div className="font-semibold line-clamp-1">{e.titel}</div>
                            <div className="opacity-80 text-[10px] mt-0.5 line-clamp-1">
                              {e.medewerker.split(" ")[0]}{e.ticket && ` · ${e.ticket}`}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </Card>

          <div className="space-y-4">
            <Card className="p-5">
              <div className="flex items-center gap-2 mb-3">
                <Users className="h-3.5 w-3.5 text-muted-foreground" />
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Team vandaag</h3>
              </div>
              <ul className="space-y-2.5">
                {teamLeden.map((m) => (
                  <li key={m.id} className="flex items-center gap-2 text-sm">
                    <div className="grid place-items-center h-7 w-7 rounded-full bg-muted text-[10px] font-semibold">{m.initialen}</div>
                    <div className="flex-1 min-w-0">
                      <div className="font-medium truncate">{m.naam}</div>
                      <div className="text-[10.5px] text-muted-foreground">{m.rol}</div>
                    </div>
                    {m.inBehandeling > 0 && (
                      <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-status-behandeling-bg text-status-behandeling">
                        {m.inBehandeling}
                      </span>
                    )}
                  </li>
                ))}
              </ul>
            </Card>

            <Card className="p-5">
              <div className="flex items-center gap-2 mb-3">
                <CalendarDays className="h-3.5 w-3.5 text-muted-foreground" />
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Legenda</h3>
              </div>
              <ul className="space-y-2 text-xs">
                {(["behandeling", "wachtend", "open", "opgelost"] as const).map((t) => (
                  <li key={t} className="flex items-center gap-2">
                    <span className="h-3 w-3 rounded" style={{ backgroundColor: `var(--status-${t})` }} />
                    <span className="capitalize">{t === "behandeling" ? "In behandeling" : t}</span>
                  </li>
                ))}
              </ul>
            </Card>
          </div>
        </div>
      </div>
    </>
  );
}
