import { createFileRoute, Link } from "@tanstack/react-router";
import { Topbar } from "@/components/topbar";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Construction, ArrowRight } from "lucide-react";

export const Route = createFileRoute("/modules/$slug")({
  head: ({ params }) => {
    const name = titleFromSlug(params.slug);
    return {
      meta: [
        { title: `${name} — Leen van Punt Intranet` },
        { name: "description", content: `Module ${name} in het Leen van Punt intranet — preview.` },
        { property: "og:title", content: `${name} — Leen van Punt Intranet` },
        { property: "og:description", content: `Module ${name} in het Leen van Punt intranet — preview.` },
      ],
    };
  },
  component: ModulePlaceholder,
});

function titleFromSlug(slug: string) {
  const map: Record<string, string> = {
    kennisbank: "Kennisbank",
    verbeterpunt: "Verbeterpunten",
    reflectie: "Reflectie",
    "hardware-uitgaven": "Hardware-uitgaven",
    medewerker: "Medewerkers",
    voorraad: "Voorraad",
    device: "Devices",
    printer: "Printers",
    cyberrisico: "CyberRisico",
    uitgifte: "Uitgifte",
    agenda: "Agenda",
    account: "Account",
    beheer: "Beheer",
    tools: "Tools",
    script: "Scripts",
    schijfgebruik: "Schijfgebruik",
  };
  return map[slug] ?? slug;
}

function ModulePlaceholder() {
  const { slug } = Route.useParams();
  const name = titleFromSlug(slug);

  return (
    <>
      <Topbar title={name} breadcrumbs={["Modules"]} />
      <div className="flex-1 overflow-y-auto p-6">
        <Card className="p-10 max-w-2xl mx-auto text-center">
          <div className="grid place-items-center h-12 w-12 rounded-xl bg-primary/10 text-primary mx-auto mb-4">
            <Construction className="h-6 w-6" />
          </div>
          <h2 className="text-xl font-semibold">Module "{name}"</h2>
          <p className="text-sm text-muted-foreground mt-2 max-w-md mx-auto">
            Deze module volgt hetzelfde patroon als Tickets: lijst met filters → detailformulier met logboek
            → gekoppelde kennisbank. Bekijk het patroon in de ticketmodule.
          </p>
          <div className="mt-6 flex items-center gap-2 justify-center">
            <Button asChild size="sm">
              <Link to="/tickets">Bekijk patroon in Tickets <ArrowRight className="h-3.5 w-3.5 ml-1.5" /></Link>
            </Button>
            <Button asChild variant="outline" size="sm">
              <Link to="/design-system">Design system</Link>
            </Button>
          </div>
        </Card>
      </div>
    </>
  );
}
