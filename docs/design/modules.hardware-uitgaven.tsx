import { createFileRoute, Link } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { StatusBadge } from "@/components/status-badge";
import { tickets } from "@/lib/mock-data";
import {
  ArrowUpRight,
  Ticket as TicketIcon,
  Clock,
  CheckCircle2,
  AlertTriangle,
  Sparkles,
  Boxes,
  Users,
  BookOpen,
  Printer,
} from "lucide-react";

export const Route = createFileRoute("/")({
  head: () => ({
    meta: [
      { title: "Dashboard — Leen van Punt Intranet" },
      { name: "description", content: "Overzicht van open tickets, KPI's en recente activiteit binnen het intranet." },
      { property: "og:title", content: "Dashboard — Leen van Punt Intranet" },
      { property: "og:description", content: "Overzicht van open tickets, KPI's en recente activiteit binnen het intranet." },
    ],
  }),
  component: Dashboard,
});

const kpis = [
  { label: "Open tickets", value: "42", delta: "+6 vs. gisteren", icon: TicketIcon, tone: "open" },
  { label: "Wacht op reactie", value: "11", delta: "3 > 24u", icon: Clock, tone: "wachtend" },
  { label: "In behandeling", value: "18", delta: "gem. 2u 14m", icon: AlertTriangle, tone: "behandeling" },
  { label: "Opgelost vandaag", value: "27", delta: "+4 vs. gem.", icon: CheckCircle2, tone: "opgelost" },
] as const;

const shortcuts = [
  { title: "Nieuw ticket", subtitle: "Handmatig aanmaken", icon: TicketIcon, to: "/tickets" },
  { title: "MailMind queue", subtitle: "8 mails wachten op review", icon: Sparkles, to: "/mailmind" },
  { title: "Voorraad", subtitle: "3 items onder minimum", icon: Boxes, to: "/modules/voorraad" },
  { title: "Medewerkers", subtitle: "Nieuwe onboarding", icon: Users, to: "/modules/medewerker" },
  { title: "Kennisbank", subtitle: "142 artikelen", icon: BookOpen, to: "/modules/kennisbank" },
  { title: "Printers", subtitle: "1 offline", icon: Printer, to: "/modules/printer" },
];

function Dashboard() {
  const recent = tickets.slice(0, 6);
  return (
    <>
      <Topbar title="Goedemorgen, Mila 👋" breadcrumbs={["Werkplek"]} />
      <div className="flex-1 overflow-y-auto p-6 space-y-6">
        {/* KPIs */}
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {kpis.map((k) => {
            const Icon = k.icon;
            return (
              <Card key={k.label} className="p-4 gap-2">
                <div className="flex items-start justify-between">
                  <div className="text-xs font-medium text-muted-foreground">{k.label}</div>
                  <div
                    className="grid place-items-center h-7 w-7 rounded-md"
                    style={{ backgroundColor: `var(--status-${k.tone}-bg)`, color: `var(--status-${k.tone})` }}
                  >
                    <Icon className="h-3.5 w-3.5" />
                  </div>
                </div>
                <div className="text-3xl font-semibold tracking-tight tabular-nums">{k.value}</div>
                <div className="text-[11px] text-muted-foreground">{k.delta}</div>
              </Card>
            );
          })}
        </section>

        <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
          {/* Recent tickets */}
          <Card className="xl:col-span-2 p-0 overflow-hidden">
            <div className="flex items-center justify-between px-5 py-3.5 border-b border-border">
              <div>
                <h2 className="text-sm font-semibold">Recente tickets</h2>
                <p className="text-xs text-muted-foreground">Toegewezen aan mij of mijn team</p>
              </div>
              <Link to="/tickets" className="text-xs text-primary hover:underline flex items-center gap-1">
                Alle tickets <ArrowUpRight className="h-3 w-3" />
              </Link>
            </div>
            <div className="divide-y divide-border">
              {recent.map((t) => (
                <Link
                  key={t.id}
                  to="/tickets/$id"
                  params={{ id: t.id }}
                  className="flex items-center gap-4 px-5 py-3 hover:bg-muted/50 transition-colors"
                >
                  <span className="text-xs font-mono text-muted-foreground w-16">{t.nummer}</span>
                  <span className="flex-1 text-sm font-medium truncate">{t.onderwerp}</span>
                  <span className="text-xs text-muted-foreground hidden md:inline w-32 truncate">{t.melder}</span>
                  <StatusBadge status={t.status} />
                  <span className="text-[11px] text-muted-foreground w-24 text-right hidden lg:inline">
                    {t.laatstBijgewerkt}
                  </span>
                </Link>
              ))}
            </div>
          </Card>

          {/* Activity feed */}
          <Card className="p-0 overflow-hidden">
            <div className="px-5 py-3.5 border-b border-border">
              <h2 className="text-sm font-semibold">Vandaag</h2>
              <p className="text-xs text-muted-foreground">Activiteit van jouw team</p>
            </div>
            <ul className="p-4 space-y-3.5 text-sm">
              {[
                { time: "10:15", who: "Jeroen", what: "zette T-2841 op in behandeling" },
                { time: "10:03", who: "MailMind", what: "publiceerde KB-artikel 'Automatisch antwoord instellen'", accent: true },
                { time: "09:41", who: "Jeroen", what: "reageerde op T-2841 (Sanne de Vries)" },
                { time: "09:22", who: "Mila", what: "wees T-2841 toe aan Jeroen" },
                { time: "08:50", who: "Faisal", what: "loste T-2833 op — backup PRD-SQL02 hersteld" },
                { time: "08:12", who: "Systeem", what: "monitorde 3 nieuwe mails via MailMind" },
              ].map((a, i) => (
                <li key={i} className="flex gap-3">
                  <span className="text-[11px] font-mono text-muted-foreground pt-0.5 w-10 shrink-0">{a.time}</span>
                  <div className="flex-1 leading-snug">
                    <span className="font-medium">{a.who}</span>{" "}
                    <span className={a.accent ? "text-primary" : "text-muted-foreground"}>{a.what}</span>
                  </div>
                </li>
              ))}
            </ul>
          </Card>
        </div>

        {/* Shortcuts */}
        <section>
          <h2 className="text-sm font-semibold mb-3">Snelkoppelingen</h2>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            {shortcuts.map((s) => {
              const Icon = s.icon;
              return (
                <Link
                  key={s.title}
                  to={s.to}
                  className="group rounded-lg border border-border bg-card p-3.5 hover:border-primary/40 hover:shadow-sm transition-all"
                >
                  <Icon className="h-4 w-4 text-primary mb-2" />
                  <div className="text-sm font-medium">{s.title}</div>
                  <div className="text-[11px] text-muted-foreground mt-0.5">{s.subtitle}</div>
                </Link>
              );
            })}
          </div>
        </section>
      </div>
    </>
  );
}
