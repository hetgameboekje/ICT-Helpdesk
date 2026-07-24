import { createFileRoute } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { UserCircle, ShieldCheck, Smartphone, MonitorSmartphone, LogOut } from "lucide-react";

export const Route = createFileRoute("/modules/account")({
  head: () => ({
    meta: [
      { title: "Account — Leen van Punt Intranet" },
      { name: "description", content: "Profielinstellingen, wachtwoord, sessies en notificatievoorkeuren." },
      { property: "og:title", content: "Account — Leen van Punt Intranet" },
      { property: "og:description", content: "Profielinstellingen, wachtwoord, sessies en notificatievoorkeuren." },
    ],
  }),
  component: AccountPage,
});

function AccountPage() {
  return (
    <>
      <Topbar title="Account" breadcrumbs={["Werkplek"]} />
      <div className="flex-1 overflow-y-auto p-6 max-w-4xl space-y-4">
        <Card className="p-6">
          <div className="flex items-center gap-4">
            <div className="grid place-items-center h-14 w-14 rounded-full bg-primary/10 text-primary text-lg font-semibold">MB</div>
            <div className="flex-1 min-w-0">
              <div className="text-lg font-semibold">Mila van den Berg</div>
              <div className="text-sm text-muted-foreground">Support · Keyuser · m.vdberg@vanpunt.nl</div>
            </div>
            <Button size="sm" variant="outline">Foto wijzigen</Button>
          </div>
        </Card>

        <Card className="p-6 space-y-4">
          <SectionHead icon={UserCircle} title="Profiel" subtitle="Persoonsgegevens die zichtbaar zijn voor collega's" />
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <Field label="Voornaam" value="Mila" />
            <Field label="Achternaam" value="van den Berg" />
            <Field label="Functie" value="Support · Keyuser" />
            <Field label="Afdeling" value="IT" />
            <Field label="Telefoon (mobiel)" value="06-12345602" />
            <Field label="Toestel" value="210" />
          </div>
          <div className="flex justify-end gap-2">
            <Button size="sm" variant="outline">Annuleer</Button>
            <Button size="sm">Opslaan</Button>
          </div>
        </Card>

        <Card className="p-6 space-y-4">
          <SectionHead icon={ShieldCheck} title="Beveiliging" subtitle="Wachtwoord en tweestapsverificatie" />
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <Field label="Huidig wachtwoord" type="password" />
            <div />
            <Field label="Nieuw wachtwoord" type="password" />
            <Field label="Herhaal nieuw wachtwoord" type="password" />
          </div>
          <div className="rounded-lg border border-border bg-muted/30 p-3 flex items-center gap-3">
            <div className="grid place-items-center h-9 w-9 rounded-md bg-primary/10 text-primary">
              <Smartphone className="h-4 w-4" />
            </div>
            <div className="flex-1 min-w-0">
              <div className="text-sm font-medium">Tweestapsverificatie via Microsoft Authenticator</div>
              <div className="text-[11px] text-muted-foreground">Actief sinds 12 maart 2025 · iPhone 15</div>
            </div>
            <Button size="sm" variant="outline">Herconfigureren</Button>
          </div>
        </Card>

        <Card className="p-6 space-y-4">
          <SectionHead icon={MonitorSmartphone} title="Actieve sessies" subtitle="Meld apparaten af waar je niet meer werkt" />
          <div className="divide-y divide-border">
            {[
              { device: "Chrome · Windows 11 · LVP-LT-0142", locatie: "Utrecht · kantoor", tijd: "nu online", huidig: true },
              { device: "Safari · iOS 18 · iPhone 15", locatie: "Utrecht · thuis", tijd: "2 uur geleden", huidig: false },
              { device: "Edge · Windows 11 · LVP-LT-0138", locatie: "Amersfoort", tijd: "gisteren 18:12", huidig: false },
            ].map((s, i) => (
              <div key={i} className="flex items-center gap-3 py-3">
                <div className="grid place-items-center h-8 w-8 rounded-md bg-muted text-muted-foreground">
                  <MonitorSmartphone className="h-4 w-4" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-medium truncate">
                    {s.device} {s.huidig && <span className="ml-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-primary/15 text-primary">huidig</span>}
                  </div>
                  <div className="text-[11px] text-muted-foreground">{s.locatie} · {s.tijd}</div>
                </div>
                {!s.huidig && (
                  <Button size="sm" variant="ghost" className="text-destructive h-8 gap-1"><LogOut className="h-3.5 w-3.5" /> Afmelden</Button>
                )}
              </div>
            ))}
          </div>
        </Card>

        <Card className="p-6 space-y-4">
          <SectionHead icon={ShieldCheck} title="Notificaties" subtitle="Waar wil je van op de hoogte blijven?" />
          {[
            { label: "Nieuwe tickets aan mij toegewezen", enabled: true },
            { label: "MailMind heeft een nieuw conceptartikel", enabled: true },
            { label: "Ticket wacht > 24u op mijn reactie", enabled: true },
            { label: "Verbeterpunt uit mijn team gewijzigd", enabled: false },
            { label: "Wekelijkse teamreflectie-samenvatting", enabled: false },
          ].map((n, i) => (
            <div key={i} className="flex items-center justify-between py-1">
              <span className="text-sm">{n.label}</span>
              <span className={`h-5 w-9 rounded-full relative ${n.enabled ? "bg-primary" : "bg-muted"}`}>
                <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white shadow ${n.enabled ? "left-[18px]" : "left-0.5"}`} />
              </span>
            </div>
          ))}
        </Card>
      </div>
    </>
  );
}

function SectionHead({ icon: Icon, title, subtitle }: { icon: React.ComponentType<{ className?: string }>; title: string; subtitle: string }) {
  return (
    <div className="flex items-center gap-3 pb-2 border-b border-border">
      <div className="grid place-items-center h-8 w-8 rounded-md bg-muted text-muted-foreground">
        <Icon className="h-4 w-4" />
      </div>
      <div>
        <div className="text-sm font-semibold">{title}</div>
        <div className="text-[11px] text-muted-foreground">{subtitle}</div>
      </div>
    </div>
  );
}

function Field({ label, value, type }: { label: string; value?: string; type?: string }) {
  return (
    <div>
      <label className="text-[11px] uppercase tracking-wider text-muted-foreground">{label}</label>
      <Input defaultValue={value} type={type} className="mt-1 h-9" />
    </div>
  );
}
