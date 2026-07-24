import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { StatusBadge } from "@/components/status-badge";
import { statusLabels, type Status } from "@/lib/mock-data";

export const Route = createFileRoute("/design-system")({
  head: () => ({
    meta: [
      { title: "Design system — Leen van Punt Intranet" },
      { name: "description", content: "Kleuren, typografie, statusbadges en componentstijl voor het intranet." },
      { property: "og:title", content: "Design system — Leen van Punt Intranet" },
      { property: "og:description", content: "Kleuren, typografie, statusbadges en componentstijl voor het intranet." },
    ],
  }),
  component: DesignSystem,
});

const semanticColors = [
  { name: "background", label: "Background" },
  { name: "foreground", label: "Foreground" },
  { name: "card", label: "Card" },
  { name: "muted", label: "Muted" },
  { name: "primary", label: "Primary — Emerald" },
  { name: "accent", label: "Accent — Amber" },
  { name: "destructive", label: "Destructive" },
  { name: "border", label: "Border" },
];

const statuses: Status[] = ["open", "wachtend", "behandeling", "opgelost", "gesloten"];
const risicos = ["laag", "gemiddeld", "hoog", "kritiek"] as const;
const risicoLbl: Record<(typeof risicos)[number], string> = {
  laag: "Laag", gemiddeld: "Gemiddeld", hoog: "Hoog", kritiek: "Kritiek",
};

function DesignSystem() {
  return (
    <>
      <Topbar title="Design system" breadcrumbs={["Systeem"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-8 max-w-6xl">
        {/* Intro */}
        <section>
          <div className="flex items-center gap-2 mb-2">
            <span className="h-1.5 w-1.5 rounded-full bg-primary" />
            <span className="text-[11px] font-semibold uppercase tracking-wider text-primary">v1.0</span>
          </div>
          <h1 className="text-3xl font-semibold tracking-tight">Leen van Punt Intranet — Design system</h1>
          <p className="text-sm text-muted-foreground mt-2 max-w-2xl">
            Een strak, dicht en snel intranet ontworpen voor mensen die de hele dag met tickets, devices
            en kennisartikelen bezig zijn. Warm off-white canvas, emerald primair, amber als accent,
            donkere sidebar. Alles rust op semantische tokens — kopieer dit patroon naar de overige modules.
          </p>
        </section>

        {/* Colors */}
        <section>
          <SectionTitle title="Kleuren" subtitle="Semantische tokens uit styles.css" />
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {semanticColors.map((c) => (
              <Card key={c.name} className="p-0 overflow-hidden">
                <div
                  className="h-20 border-b border-border"
                  style={{ backgroundColor: `var(--${c.name})` }}
                />
                <div className="p-3">
                  <div className="text-sm font-medium">{c.label}</div>
                  <div className="text-[11px] font-mono text-muted-foreground">--{c.name}</div>
                </div>
              </Card>
            ))}
          </div>
        </section>

        {/* Status system */}
        <section>
          <SectionTitle
            title="Statussysteem"
            subtitle="Één betekenis, overal dezelfde kleur — geldt voor tickets, verbeterpunten, cyberrisico, uitgifte, enz."
          />
          <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
            {statuses.map((s) => (
              <Card key={s} className="p-4 gap-2">
                <StatusBadge status={s} />
                <div className="text-xs text-muted-foreground mt-1">{statusLabels[s]}</div>
                <div className="mt-2 flex items-center gap-1.5">
                  <span className="h-4 w-4 rounded" style={{ backgroundColor: `var(--status-${s})` }} />
                  <span className="h-4 w-4 rounded border border-border" style={{ backgroundColor: `var(--status-${s}-bg)` }} />
                </div>
                <div className="text-[10.5px] font-mono text-muted-foreground">--status-{s}</div>
              </Card>
            ))}
          </div>
        </section>

        {/* Risico-schaal */}
        <section>
          <SectionTitle
            title="CyberRisico-schaal"
            subtitle="Eigen warm/rood-schaal die niet botst met de ticket-statuskleuren. Alleen gebruiken binnen de CyberRisico-module."
          />
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {risicos.map((r) => (
              <Card key={r} className="p-4 gap-2">
                <span
                  className="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium w-fit ring-1 ring-inset"
                  style={{ backgroundColor: `var(--risk-${r}-bg)`, color: `var(--risk-${r})` }}
                >
                  <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: `var(--risk-${r})` }} />
                  {risicoLbl[r]}
                </span>
                <div className="mt-2 flex items-center gap-1.5">
                  <span className="h-4 w-4 rounded" style={{ backgroundColor: `var(--risk-${r})` }} />
                  <span className="h-4 w-4 rounded border border-border" style={{ backgroundColor: `var(--risk-${r}-bg)` }} />
                </div>
                <div className="text-[10.5px] font-mono text-muted-foreground">--risk-{r}</div>
              </Card>
            ))}
          </div>
        </section>

        {/* Voorraad-indicatoren */}
        <section>
          <SectionTitle title="Voorraad-indicatoren" subtitle="Alleen voor voorraadniveau — 'onder minimum' rood, 'op peil' emerald" />
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {(["ok", "low"] as const).map((s) => (
              <Card key={s} className="p-4 gap-2">
                <div className="text-sm font-medium">{s === "ok" ? "Op peil" : "Onder minimum"}</div>
                <div className="flex items-center gap-1.5">
                  <span className="h-4 w-4 rounded" style={{ backgroundColor: `var(--stock-${s})` }} />
                  <span className="h-4 w-4 rounded border border-border" style={{ backgroundColor: `var(--stock-${s}-bg)` }} />
                </div>
                <div className="text-[10.5px] font-mono text-muted-foreground">--stock-{s}</div>
                <div className="h-1.5 rounded-full bg-muted overflow-hidden mt-1">
                  <div className="h-full" style={{ width: s === "ok" ? "75%" : "22%", backgroundColor: `var(--stock-${s})` }} />
                </div>
              </Card>
            ))}
          </div>
        </section>
        <section>
          <SectionTitle title="Typografie" subtitle="Inter voor UI, JetBrains Mono voor codes/ID's" />
          <Card className="p-6 space-y-4">
            <div>
              <div className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">Display / 30 · 600</div>
              <div className="text-3xl font-semibold tracking-tight">Onboarding nieuwe medewerker</div>
            </div>
            <div>
              <div className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">Section / 15 · 600</div>
              <div className="text-[15px] font-semibold">Ticket T-2841 — Outlook synchroniseert niet</div>
            </div>
            <div>
              <div className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">Body / 14 · 400</div>
              <div className="text-sm text-foreground/90 max-w-xl">
                Hoi support, sinds vanochtend werkt mijn Outlook niet meer. Kunnen jullie even helpen? Ik moet
                vandaag een offerte de deur uit doen.
              </div>
            </div>
            <div>
              <div className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">Caption / 11 · 500 · uppercase</div>
              <div className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">Aangemaakt door</div>
            </div>
            <div>
              <div className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">Mono / 12 · 500 — voor ID's</div>
              <div className="font-mono text-xs">T-2841 · KB-142 · VP-034 · M-8817</div>
            </div>
          </Card>
        </section>

        {/* Components */}
        <section>
          <SectionTitle title="Componenten" subtitle="Bouwstenen die overal terugkomen" />
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <Card className="p-5">
              <div className="text-xs font-medium text-muted-foreground mb-3">Knoppen</div>
              <div className="flex flex-wrap gap-2">
                <Button size="sm">Primair</Button>
                <Button size="sm" variant="secondary">Secundair</Button>
                <Button size="sm" variant="outline">Outline</Button>
                <Button size="sm" variant="ghost">Ghost</Button>
                <Button size="sm" variant="destructive">Destructief</Button>
              </div>
            </Card>
            <Card className="p-5">
              <div className="text-xs font-medium text-muted-foreground mb-3">Invoervelden</div>
              <div className="space-y-2">
                <Input placeholder="Zoek op naam, e-mail of afdeling…" />
                <div className="flex gap-2">
                  <Input placeholder="Voornaam" />
                  <Input placeholder="Achternaam" />
                </div>
              </div>
            </Card>
            <Card className="p-5">
              <div className="text-xs font-medium text-muted-foreground mb-3">Badges & pills</div>
              <div className="flex flex-wrap gap-2">
                {statuses.map((s) => (
                  <StatusBadge key={s} status={s} />
                ))}
              </div>
            </Card>
            <Card className="p-5">
              <div className="text-xs font-medium text-muted-foreground mb-3">Patroon: lijst → detail → logboek</div>
              <p className="text-xs text-muted-foreground leading-relaxed">
                Elke module (tickets, verbeterpunten, cyberrisico, uitgifte, hardware…) volgt hetzelfde ritme:
                een compacte tabel met statusbadges en filters, een detailscherm met formulier links en een
                sidebar rechts, en een tijdlijn onderaan met kleurgecodeerde eventtypes. Herbruikbaar,
                voorspelbaar, snel te scannen.
              </p>
            </Card>
          </div>
        </section>
      </div>
    </>
  );
}

function SectionTitle({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <div className="mb-4">
      <h2 className="text-lg font-semibold tracking-tight">{title}</h2>
      <p className="text-xs text-muted-foreground mt-0.5">{subtitle}</p>
    </div>
  );
}
