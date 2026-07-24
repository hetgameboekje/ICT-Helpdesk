import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import { reflecties } from "@/lib/mock-data";
import { MessageSquareQuote, Plus } from "lucide-react";
import { useState } from "react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/modules/reflectie")({
  head: () => ({
    meta: [
      { title: "Reflectie — Leen van Punt Intranet" },
      { name: "description", content: "Korte reflectienotities van het supportteam — leren van patronen in tickets." },
      { property: "og:title", content: "Reflectie — Leen van Punt Intranet" },
      { property: "og:description", content: "Korte reflectienotities van het supportteam." },
    ],
  }),
  component: ReflectiePage,
});

function ReflectiePage() {
  const [selId, setSelId] = useState(reflecties[0].id);
  const sel = reflecties.find((r) => r.id === selId)!;

  return (
    <>
      <Topbar title="Reflectie" breadcrumbs={["Support"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-4 max-w-6xl">
        <Card className="p-5 flex items-center gap-4 bg-gradient-to-br from-primary/5 to-accent/10 border-primary/20">
          <div className="grid place-items-center h-11 w-11 rounded-lg bg-primary text-primary-foreground">
            <MessageSquareQuote className="h-5 w-5" />
          </div>
          <div className="flex-1">
            <div className="text-sm font-semibold">Wat viel op deze week?</div>
            <p className="text-xs text-muted-foreground mt-0.5 max-w-xl">
              Reflectie is een lichte referentiemodule: iedereen legt kort patronen vast die we in andere modules (Ticket, Verbeterpunt) kunnen oppakken.
            </p>
          </div>
          <Button size="sm" className="gap-1.5"><Plus className="h-3.5 w-3.5" /> Nieuwe reflectie</Button>
        </Card>

        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,360px)_1fr] gap-4">
          <Card className="p-0 overflow-hidden">
            <div className="divide-y divide-border">
              {reflecties.map((r) => (
                <button
                  key={r.id}
                  onClick={() => setSelId(r.id)}
                  className={cn("w-full text-left px-4 py-3 hover:bg-muted/40", sel.id === r.id && "bg-muted/60")}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium line-clamp-1">{r.onderwerp}</span>
                    <span className="text-[11px] font-mono text-muted-foreground shrink-0 ml-2">{r.datum}</span>
                  </div>
                  <div className="text-[11px] text-muted-foreground mt-0.5">{r.auteur}</div>
                </button>
              ))}
            </div>
          </Card>

          <Card className="p-6 space-y-4">
            <div>
              <div className="text-[11px] text-muted-foreground">{sel.datum} · {sel.auteur}</div>
              <Input defaultValue={sel.onderwerp} className="mt-2 text-xl font-semibold tracking-tight border-0 shadow-none px-0 h-auto py-1 focus-visible:ring-0" />
            </div>
            <Textarea defaultValue={sel.notitie} rows={8} className="text-sm leading-relaxed" />
            <div className="flex items-center justify-end gap-2">
              <Button size="sm" variant="outline">Annuleer</Button>
              <Button size="sm">Opslaan</Button>
            </div>
          </Card>
        </div>
      </div>
    </>
  );
}
