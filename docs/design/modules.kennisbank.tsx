import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { mailmindQueue, type MailMindItem } from "@/lib/mock-data";
import {
  Inbox,
  Brain,
  FileEdit,
  CheckCircle2,
  ArrowRight,
  Sparkles,
  Mail,
  Eye,
} from "lucide-react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/mailmind")({
  head: () => ({
    meta: [
      { title: "MailMind — Leen van Punt Intranet" },
      { name: "description", content: "AI-pijplijn die inkomende mails classificeert en omzet in kennisbankartikelen." },
      { property: "og:title", content: "MailMind — E-mail naar kennisbank via AI" },
      { property: "og:description", content: "AI-pijplijn die inkomende mails classificeert en omzet in kennisbankartikelen." },
    ],
  }),
  component: MailMind,
});

type Fase = MailMindItem["fase"];

const stages: {
  key: Fase;
  title: string;
  subtitle: string;
  icon: React.ComponentType<{ className?: string }>;
  tone: string;
}[] = [
  { key: "ontvangen", title: "Ontvangen", subtitle: "Uit e-mailqueue", icon: Inbox, tone: "open" },
  { key: "geanalyseerd", title: "AI-analyse", subtitle: "Classificatie & samenvatting", icon: Brain, tone: "behandeling" },
  { key: "concept", title: "Conceptartikel", subtitle: "Wacht op review", icon: FileEdit, tone: "wachtend" },
  { key: "gepubliceerd", title: "Gepubliceerd", subtitle: "Live in kennisbank", icon: CheckCircle2, tone: "opgelost" },
];

function MailMind() {
  const byFase: Record<Fase, MailMindItem[]> = {
    ontvangen: mailmindQueue.filter((m) => m.fase === "ontvangen"),
    geanalyseerd: mailmindQueue.filter((m) => m.fase === "geanalyseerd"),
    concept: mailmindQueue.filter((m) => m.fase === "concept"),
    gepubliceerd: mailmindQueue.filter((m) => m.fase === "gepubliceerd"),
  };

  return (
    <>
      <Topbar title="MailMind" breadcrumbs={["Support", "Automation"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-6">
        {/* Intro */}
        <Card className="p-5 flex flex-col md:flex-row md:items-center gap-4 bg-gradient-to-br from-primary/5 to-accent/10 border-primary/20">
          <div className="grid place-items-center h-11 w-11 rounded-lg bg-primary text-primary-foreground">
            <Sparkles className="h-5 w-5" />
          </div>
          <div className="flex-1">
            <div className="text-sm font-semibold">E-mail → AI-classificatie → Kennisbankconcept</div>
            <p className="text-xs text-muted-foreground mt-0.5 max-w-2xl">
              MailMind leest binnenkomende support-mails, herkent het onderwerp en stelt automatisch een
              conceptartikel voor. Jij hoeft alleen te reviewen en te publiceren.
            </p>
          </div>
          <div className="flex items-center gap-6 text-center">
            <div>
              <div className="text-xl font-semibold tabular-nums">247</div>
              <div className="text-[10px] text-muted-foreground uppercase tracking-wider">Deze week</div>
            </div>
            <div>
              <div className="text-xl font-semibold tabular-nums text-primary">92%</div>
              <div className="text-[10px] text-muted-foreground uppercase tracking-wider">Vertrouwen</div>
            </div>
            <div>
              <div className="text-xl font-semibold tabular-nums">38</div>
              <div className="text-[10px] text-muted-foreground uppercase tracking-wider">Gepubliceerd</div>
            </div>
          </div>
        </Card>

        {/* Pipeline */}
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-4 relative">
          {stages.map((stage, i) => {
            const Icon = stage.icon;
            const items = byFase[stage.key];
            return (
              <div key={stage.key} className="relative flex flex-col">
                {/* Column header */}
                <div className="flex items-center gap-3 mb-3">
                  <div
                    className="grid place-items-center h-8 w-8 rounded-lg shrink-0"
                    style={{
                      backgroundColor: `var(--status-${stage.tone}-bg)`,
                      color: `var(--status-${stage.tone})`,
                    }}
                  >
                    <Icon className="h-4 w-4" />
                  </div>
                  <div className="flex-1">
                    <div className="text-sm font-semibold flex items-center gap-2">
                      {stage.title}
                      <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-muted text-muted-foreground">
                        {items.length}
                      </span>
                    </div>
                    <div className="text-[11px] text-muted-foreground">{stage.subtitle}</div>
                  </div>
                </div>

                {/* Arrow between columns */}
                {i < stages.length - 1 && (
                  <ArrowRight className="hidden lg:block absolute -right-3 top-3 h-4 w-4 text-muted-foreground/40 z-10" />
                )}

                {/* Cards */}
                <div className="flex-1 space-y-2 rounded-lg bg-muted/30 border border-dashed border-border p-2 min-h-[300px]">
                  {items.map((m) => (
                    <PipelineCard key={m.id} item={m} />
                  ))}
                  {items.length === 0 && (
                    <div className="text-center text-[11px] text-muted-foreground py-8">Leeg</div>
                  )}
                </div>
              </div>
            );
          })}
        </div>

        {/* Latest concept preview */}
        <Card className="p-0 overflow-hidden">
          <div className="px-5 py-3.5 border-b border-border flex items-center justify-between">
            <div>
              <h2 className="text-sm font-semibold">Laatste conceptartikel</h2>
              <p className="text-xs text-muted-foreground">
                Gegenereerd uit e-mail van e.hendriks@vanpunt.nl · vertrouwen 94%
              </p>
            </div>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" className="h-8 text-xs">
                <Eye className="h-3 w-3 mr-1.5" /> Preview
              </Button>
              <Button size="sm" className="h-8 text-xs">Publiceer naar KB</Button>
            </div>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-border">
            <div className="p-5">
              <div className="flex items-center gap-2 text-[11px] text-muted-foreground mb-2">
                <Mail className="h-3.5 w-3.5" /> ORIGINEEL BERICHT
              </div>
              <p className="text-sm leading-relaxed text-foreground/80">
                Hoi, ik ga volgende week met vakantie en wil een automatisch antwoord instellen. Kan iemand
                me vertellen hoe dat werkt op mijn laptop én in de webversie? Ik snap niet waar dat menu zit.
                Alvast bedankt!
              </p>
            </div>
            <div className="p-5">
              <div className="flex items-center gap-2 text-[11px] text-primary mb-2">
                <Sparkles className="h-3.5 w-3.5" /> AI-CONCEPTARTIKEL
              </div>
              <h3 className="text-base font-semibold mb-2">
                Automatisch antwoord instellen in Outlook (web + desktop)
              </h3>
              <ol className="text-sm space-y-1.5 text-foreground/80 list-decimal list-inside">
                <li>Open Outlook en ga naar <em>Bestand → Automatische antwoorden</em>.</li>
                <li>Vink <em>Automatische antwoorden verzenden</em> aan en kies een periode.</li>
                <li>Vul het bericht in voor collega's (binnen organisatie) en klanten (buiten).</li>
                <li>In de webversie: tandwiel-icoon → <em>Alle instellingen bekijken → E-mail → Automatische antwoorden</em>.</li>
              </ol>
            </div>
          </div>
        </Card>
      </div>
    </>
  );
}

function PipelineCard({ item }: { item: MailMindItem }) {
  return (
    <div className="group rounded-md bg-card border border-border p-2.5 hover:border-primary/40 hover:shadow-sm transition-all cursor-pointer">
      <div className="flex items-center justify-between mb-1">
        <span className="font-mono text-[10px] text-muted-foreground">{item.id}</span>
        {item.vertrouwen !== undefined && (
          <span
            className={cn(
              "text-[10px] font-medium px-1.5 rounded",
              item.vertrouwen >= 95 ? "bg-status-opgelost-bg text-status-opgelost" :
              item.vertrouwen >= 85 ? "bg-status-behandeling-bg text-status-behandeling" :
              "bg-status-wachtend-bg text-status-wachtend"
            )}
          >
            {item.vertrouwen}%
          </span>
        )}
      </div>
      <div className="text-xs font-medium leading-snug line-clamp-2 group-hover:text-primary">
        {item.onderwerp}
      </div>
      <div className="text-[10.5px] text-muted-foreground mt-1 truncate">{item.van}</div>
      <div className="flex items-center justify-between mt-1.5">
        {item.categorie && (
          <span className="text-[10px] px-1.5 py-0.5 rounded bg-muted text-muted-foreground">
            {item.categorie}
          </span>
        )}
        <span className="text-[10px] text-muted-foreground/70 ml-auto">{item.ontvangen}</span>
      </div>
      {item.kbConcept && (
        <div className="mt-2 pt-2 border-t border-border text-[11px] text-foreground/80 leading-snug">
          <FileEdit className="inline h-3 w-3 mr-1 text-primary" />
          {item.kbConcept}
        </div>
      )}
    </div>
  );
}
