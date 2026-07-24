import { createFileRoute, Link, notFound } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { StatusBadge, PriorityPill } from "@/components/status-badge";
import { tickets, ticketLog, kbSuggesties, statusLabels, type Status } from "@/lib/mock-data";
import {
  ArrowLeft,
  Mail,
  MessageSquare,
  Paperclip,
  BookOpen,
  Sparkles,
  Send,
  MoreHorizontal,
  UserCircle2,
  Clock,
  Tag,
  Building2,
  RefreshCw,
  CircleCheck,
  StickyNote,
  AtSign,
} from "lucide-react";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/tickets/$id")({
  loader: ({ params }) => {
    const ticket = tickets.find((t) => t.id === params.id);
    if (!ticket) throw notFound();
    return { ticket };
  },
  head: ({ loaderData }) => ({
    meta: [
      { title: loaderData ? `${loaderData.ticket.nummer} · ${loaderData.ticket.onderwerp}` : "Ticket" },
      { name: "description", content: "Ticketdetail met formulier, logboek en gekoppelde kennisbankartikelen." },
      { property: "og:title", content: loaderData ? `${loaderData.ticket.nummer} · Ticket` : "Ticket" },
      { property: "og:description", content: "Ticketdetail met formulier, logboek en gekoppelde kennisbankartikelen." },
    ],
  }),
  component: TicketDetail,
});

const logIcons = {
  status: RefreshCw,
  reactie: Send,
  intern: StickyNote,
  systeem: Sparkles,
  email: Mail,
} as const;

function TicketDetail() {
  const { ticket } = Route.useLoaderData();

  return (
    <>
      <Topbar
        title={`${ticket.nummer} — ${ticket.onderwerp}`}
        breadcrumbs={["Support", "Tickets"]}
      />
      <div className="flex-1 overflow-y-auto">
        {/* Sub-header */}
        <div className="flex items-center gap-3 px-6 py-3 border-b border-border bg-background">
          <Link
            to="/tickets"
            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
          >
            <ArrowLeft className="h-3.5 w-3.5" /> Terug
          </Link>
          <span className="text-muted-foreground/40">·</span>
          <StatusBadge status={ticket.status} />
          <PriorityPill prioriteit={ticket.prioriteit} />
          <div className="flex-1" />
          <Button variant="outline" size="sm" className="h-8 gap-1.5">
            <CircleCheck className="h-3.5 w-3.5" /> Markeer opgelost
          </Button>
          <Button variant="outline" size="sm" className="h-8 gap-1.5">
            <UserCircle2 className="h-3.5 w-3.5" /> Toewijzen
          </Button>
          <Button variant="ghost" size="icon" className="h-8 w-8">
            <MoreHorizontal className="h-4 w-4" />
          </Button>
        </div>

        <div className="grid grid-cols-1 xl:grid-cols-[1fr_340px] gap-6 p-6">
          {/* Main column */}
          <div className="space-y-6 min-w-0">
            {/* Description */}
            <Card className="p-5">
              <div className="flex items-center gap-2 mb-3">
                <Mail className="h-4 w-4 text-muted-foreground" />
                <span className="text-xs font-medium text-muted-foreground">
                  Oorspronkelijk bericht van {ticket.melder}
                </span>
              </div>
              <p className="text-sm leading-relaxed text-foreground/90">
                Hoi support, sinds vanochtend werkt mijn Outlook niet meer. Hij loopt vast bij het openen en
                mail komt niet binnen. Ik heb al opnieuw opgestart maar niks veranderd. Foutmelding zegt
                iets met een profiel dat niet gevonden kan worden. Kunnen jullie even helpen? Ik moet vandaag
                een offerte de deur uit doen.
              </p>
              <p className="text-sm leading-relaxed text-foreground/90 mt-3">
                Groet,<br />Sanne
              </p>
              <div className="mt-4 pt-3 border-t border-border flex items-center gap-3 text-xs text-muted-foreground">
                <Paperclip className="h-3.5 w-3.5" />
                <span>screenshot-fout.png · 214 KB</span>
              </div>
            </Card>

            {/* Reply composer */}
            <Card className="p-0 overflow-hidden">
              <div className="flex items-center border-b border-border px-1">
                {[
                  { icon: Send, label: "Antwoorden", active: true },
                  { icon: StickyNote, label: "Interne notitie" },
                  { icon: AtSign, label: "Vermelding" },
                ].map((t) => (
                  <button
                    key={t.label}
                    className={cn(
                      "flex items-center gap-1.5 px-3 py-2.5 text-xs font-medium border-b-2 -mb-px transition-colors",
                      t.active
                        ? "border-primary text-foreground"
                        : "border-transparent text-muted-foreground hover:text-foreground",
                    )}
                  >
                    <t.icon className="h-3.5 w-3.5" />
                    {t.label}
                  </button>
                ))}
              </div>
              <div className="p-4">
                <Textarea
                  rows={4}
                  placeholder="Schrijf een reactie aan de melder…"
                  className="border-0 focus-visible:ring-0 shadow-none p-0 resize-none text-sm"
                />
              </div>
              <div className="flex items-center justify-between border-t border-border px-4 py-2.5 bg-muted/30">
                <div className="flex items-center gap-1 text-muted-foreground">
                  <Button variant="ghost" size="icon" className="h-7 w-7"><Paperclip className="h-3.5 w-3.5" /></Button>
                  <Button variant="ghost" size="sm" className="h-7 text-xs gap-1.5">
                    <BookOpen className="h-3.5 w-3.5" /> KB-artikel invoegen
                  </Button>
                </div>
                <div className="flex items-center gap-2">
                  <Button variant="outline" size="sm" className="h-8 text-xs">Concept</Button>
                  <Button size="sm" className="h-8 text-xs gap-1.5">
                    <Send className="h-3 w-3" /> Verstuur reactie
                  </Button>
                </div>
              </div>
            </Card>

            {/* Timeline / Logboek */}
            <Card className="p-0 overflow-hidden">
              <div className="px-5 py-3.5 border-b border-border flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <MessageSquare className="h-4 w-4 text-muted-foreground" />
                  <h2 className="text-sm font-semibold">Logboek & tijdlijn</h2>
                </div>
                <span className="text-[11px] text-muted-foreground">{ticketLog.length} gebeurtenissen</span>
              </div>
              <ol className="p-5 space-y-4">
                {ticketLog.map((entry, i) => {
                  const Icon = logIcons[entry.type];
                  return (
                    <li key={i} className="flex gap-3 relative">
                      {i !== ticketLog.length - 1 && (
                        <span className="absolute left-3.5 top-8 bottom-[-16px] w-px bg-border" />
                      )}
                      <div
                        className={cn(
                          "grid place-items-center h-7 w-7 rounded-full shrink-0 z-10",
                          entry.type === "systeem" && "bg-primary/10 text-primary",
                          entry.type === "email" && "bg-status-open-bg text-status-open",
                          entry.type === "reactie" && "bg-status-behandeling-bg text-status-behandeling",
                          entry.type === "intern" && "bg-status-wachtend-bg text-status-wachtend",
                          entry.type === "status" && "bg-muted text-muted-foreground",
                        )}
                      >
                        <Icon className="h-3.5 w-3.5" />
                      </div>
                      <div className="flex-1 min-w-0 pt-0.5">
                        <div className="flex items-baseline gap-2 flex-wrap">
                          <span className="text-sm font-medium">{entry.actor}</span>
                          <span className="text-sm text-muted-foreground">{entry.actie}</span>
                          <span className="text-[11px] text-muted-foreground/70 ml-auto">{entry.tijd}</span>
                        </div>
                        {entry.detail && (
                          <div
                            className={cn(
                              "mt-1.5 text-sm leading-relaxed rounded-md p-2.5",
                              entry.type === "intern"
                                ? "bg-status-wachtend-bg/60 text-foreground/90 border border-status-wachtend/20"
                                : "bg-muted/40 text-foreground/80",
                            )}
                          >
                            {entry.detail}
                          </div>
                        )}
                      </div>
                    </li>
                  );
                })}
              </ol>
            </Card>
          </div>

          {/* Right column */}
          <div className="space-y-4">
            <Card className="p-5">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-4">
                Details
              </h3>
              <dl className="space-y-3 text-sm">
                <DetailRow icon={UserCircle2} label="Melder" value={ticket.melder} sub={ticket.afdeling} />
                <DetailRow icon={UserCircle2} label="Behandelaar" value={ticket.behandelaar} />
                <DetailRow icon={Building2} label="Afdeling" value={ticket.afdeling} />
                <DetailRow icon={Tag} label="Categorie" value={ticket.categorie} />
                <DetailRow icon={Mail} label="Kanaal" value={ticket.kanaal} />
                <DetailRow icon={Clock} label="Aangemaakt" value={ticket.aangemaakt} />
                <DetailRow icon={RefreshCw} label="Bijgewerkt" value={ticket.laatstBijgewerkt} />
              </dl>
            </Card>

            <Card className="p-5">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Status wijzigen
                </h3>
              </div>
              <div className="grid grid-cols-2 gap-1.5">
                {(["open", "wachtend", "behandeling", "opgelost", "gesloten"] as Status[]).map((s) => (
                  <button
                    key={s}
                    className={cn(
                      "px-2 py-1.5 text-xs rounded-md ring-1 ring-inset text-left transition-all hover:brightness-95",
                      s === ticket.status ? "ring-2" : "opacity-70 hover:opacity-100",
                    )}
                    style={{
                      backgroundColor: `var(--status-${s}-bg)`,
                      color: `var(--status-${s})`,
                    }}
                  >
                    {statusLabels[s]}
                  </button>
                ))}
              </div>
            </Card>

            <Card className="p-5">
              <div className="flex items-center gap-2 mb-3">
                <Sparkles className="h-3.5 w-3.5 text-primary" />
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                  Kennisbank — AI-suggesties
                </h3>
              </div>
              <ul className="space-y-2">
                {kbSuggesties.map((kb) => (
                  <li
                    key={kb.id}
                    className="group rounded-md border border-border p-2.5 hover:border-primary/40 hover:bg-muted/40 transition-all cursor-pointer"
                  >
                    <div className="flex items-center gap-2">
                      <BookOpen className="h-3.5 w-3.5 text-muted-foreground" />
                      <span className="font-mono text-[10px] text-muted-foreground">{kb.id}</span>
                      {!kb.actueel && (
                        <span className="text-[10px] px-1.5 rounded bg-status-wachtend-bg text-status-wachtend">
                          verouderd
                        </span>
                      )}
                    </div>
                    <div className="text-sm font-medium mt-1 leading-snug group-hover:text-primary">
                      {kb.titel}
                    </div>
                    <div className="text-[11px] text-muted-foreground mt-0.5">{kb.views} views</div>
                  </li>
                ))}
              </ul>
            </Card>

            <Card className="p-5">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                Gerelateerd
              </h3>
              <ul className="space-y-2 text-sm">
                <li className="flex items-center gap-2 text-muted-foreground hover:text-foreground cursor-pointer">
                  <span className="font-mono text-[10px]">T-2701</span> Outlook profielprobleem — Van Elst
                </li>
                <li className="flex items-center gap-2 text-muted-foreground hover:text-foreground cursor-pointer">
                  <span className="font-mono text-[10px]">VP-034</span> Verbeterpunt: OST-herbouw automatiseren
                </li>
              </ul>
            </Card>
          </div>
        </div>
      </div>
    </>
  );
}

function DetailRow({
  icon: Icon,
  label,
  value,
  sub,
}: {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  value: string;
  sub?: string;
}) {
  return (
    <div className="flex items-start gap-3">
      <Icon className="h-3.5 w-3.5 mt-0.5 text-muted-foreground shrink-0" />
      <div className="flex-1 min-w-0">
        <dt className="text-[11px] text-muted-foreground">{label}</dt>
        <dd className="text-sm font-medium truncate">{value}</dd>
        {sub && <dd className="text-[11px] text-muted-foreground">{sub}</dd>}
      </div>
    </div>
  );
}
