import { createFileRoute, Link } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { kbArtikelen } from "@/lib/mock-data";
import { BookOpen, Search, Plus, Sparkles, Check, X, Eye, Clock, User } from "lucide-react";
import { useMemo, useState } from "react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/modules/kennisbank")({
  head: () => ({
    meta: [
      { title: "Kennisbank — Leen van Punt Intranet" },
      { name: "description", content: "Interne kennisbank met artikelen, AI-conceptartikelen en koppeling aan tickets." },
      { property: "og:title", content: "Kennisbank — Leen van Punt Intranet" },
      { property: "og:description", content: "Interne kennisbank met artikelen, AI-conceptartikelen en koppeling aan tickets." },
    ],
  }),
  component: Kennisbank,
});

function Kennisbank() {
  const [q, setQ] = useState("");
  const [selId, setSelId] = useState(kbArtikelen[0].id);
  const [filter, setFilter] = useState<"alle" | "gepubliceerd" | "concept">("alle");

  const filtered = useMemo(
    () =>
      kbArtikelen.filter(
        (a) =>
          (filter === "alle" || (filter === "concept" ? a.concept : !a.concept)) &&
          (q === "" || a.titel.toLowerCase().includes(q.toLowerCase()) || a.categorie.toLowerCase().includes(q.toLowerCase())),
      ),
    [q, filter],
  );
  const sel = kbArtikelen.find((a) => a.id === selId) ?? filtered[0];

  const kpis = [
    { label: "Artikelen", value: kbArtikelen.filter((a) => !a.concept).length, icon: BookOpen, tone: "opgelost" },
    { label: "AI-concepten", value: kbArtikelen.filter((a) => a.concept).length, icon: Sparkles, tone: "wachtend" },
    { label: "Views deze week", value: "1.284", icon: Eye, tone: "open" },
    { label: "Verouderd (>6 mnd)", value: 1, icon: Clock, tone: "gesloten" },
  ];

  return (
    <>
      <Topbar title="Kennisbank" breadcrumbs={["Support"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4">
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((k) => (
            <Kpi key={k.label} {...k} />
          ))}
        </section>

        <div className="flex flex-col lg:flex-row gap-3">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Zoek in titels, categorieën…" className="pl-9 h-9" />
          </div>
          <div className="flex items-center gap-1">
            {(["alle", "gepubliceerd", "concept"] as const).map((f) => (
              <button
                key={f}
                onClick={() => setFilter(f)}
                className={cn(
                  "px-3 py-1.5 rounded-md text-xs font-medium capitalize",
                  filter === f ? "bg-foreground text-background" : "text-muted-foreground hover:bg-muted",
                )}
              >
                {f}
              </button>
            ))}
            <Button size="sm" className="h-9 gap-1.5 ml-2"><Plus className="h-3.5 w-3.5" /> Nieuw artikel</Button>
          </div>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,420px)_1fr] gap-4">
          <Card className="p-0 overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-[11px] uppercase tracking-wider text-muted-foreground border-b border-border bg-muted/40">
                  <th className="text-left font-semibold px-4 py-2.5">Artikel</th>
                  <th className="text-right font-semibold px-4 py-2.5 w-24">Views</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {filtered.map((a) => (
                  <tr
                    key={a.id}
                    onClick={() => setSelId(a.id)}
                    className={cn("cursor-pointer hover:bg-muted/40", sel?.id === a.id && "bg-muted/60")}
                  >
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-[10px] text-muted-foreground">{a.id}</span>
                        {a.concept && (
                          <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-status-wachtend-bg text-status-wachtend">
                            <Sparkles className="h-2.5 w-2.5" /> concept · AI
                          </span>
                        )}
                      </div>
                      <div className="text-sm font-medium mt-0.5 line-clamp-1">{a.titel}</div>
                      <div className="text-[11px] text-muted-foreground mt-0.5">{a.categorie} · {a.auteur} · {a.bijgewerkt}</div>
                    </td>
                    <td className="px-4 py-3 text-right text-xs font-mono text-muted-foreground">{a.views.toLocaleString("nl-NL")}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>

          {sel && (
            <Card className="p-6 space-y-4">
              <div className="flex items-start justify-between gap-4">
                <div className="min-w-0">
                  <div className="flex items-center gap-2 text-[11px] text-muted-foreground mb-1">
                    <span className="font-mono">{sel.id}</span> · {sel.categorie}
                    {sel.concept && (
                      <span className="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-status-wachtend-bg text-status-wachtend">
                        <Sparkles className="h-2.5 w-2.5" /> AI-concept uit {sel.bron}
                      </span>
                    )}
                  </div>
                  <h2 className="text-xl font-semibold tracking-tight">{sel.titel}</h2>
                  <div className="text-[11px] text-muted-foreground mt-1 flex items-center gap-3">
                    <span className="flex items-center gap-1"><User className="h-3 w-3" /> {sel.auteur}</span>
                    <span className="flex items-center gap-1"><Clock className="h-3 w-3" /> {sel.bijgewerkt}</span>
                    <span className="flex items-center gap-1"><Eye className="h-3 w-3" /> {sel.views.toLocaleString("nl-NL")} views</span>
                  </div>
                </div>
                {sel.concept ? (
                  <div className="flex items-center gap-2 shrink-0">
                    <Button size="sm" variant="outline" className="h-8 gap-1.5"><X className="h-3.5 w-3.5" /> Afwijzen</Button>
                    <Button size="sm" className="h-8 gap-1.5"><Check className="h-3.5 w-3.5" /> Goedkeuren</Button>
                  </div>
                ) : (
                  <Button size="sm" variant="outline" className="h-8">Bewerken</Button>
                )}
              </div>

              <div className="text-sm leading-relaxed text-foreground/90 whitespace-pre-wrap border-t border-border pt-4">
                {sel.inhoud}
              </div>

              <div className="border-t border-border pt-4">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                  Gekoppelde tickets
                </h3>
                {sel.gekoppeldeTickets.length === 0 ? (
                  <p className="text-xs text-muted-foreground">Nog geen tickets aan dit artikel gekoppeld.</p>
                ) : (
                  <ul className="space-y-1.5">
                    {sel.gekoppeldeTickets.map((t) => (
                      <li key={t}>
                        <Link to="/tickets" className="inline-flex items-center gap-2 text-sm hover:text-primary">
                          <span className="font-mono text-[11px] text-muted-foreground">{t}</span>
                          <span className="text-muted-foreground">— openen in tickets</span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </Card>
          )}
        </div>
      </div>
    </>
  );
}

function Kpi({ label, value, icon: Icon, tone }: { label: string; value: string | number; icon: React.ComponentType<{ className?: string }>; tone: string }) {
  return (
    <Card className="p-4 gap-2">
      <div className="flex items-start justify-between">
        <div className="text-xs font-medium text-muted-foreground">{label}</div>
        <div className="grid place-items-center h-7 w-7 rounded-md" style={{ backgroundColor: `var(--status-${tone}-bg)`, color: `var(--status-${tone})` }}>
          <Icon className="h-3.5 w-3.5" />
        </div>
      </div>
      <div className="text-3xl font-semibold tracking-tight tabular-nums">{value}</div>
    </Card>
  );
}
