import { Bell, Command, HelpCircle, Plus } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

export function Topbar({ title, breadcrumbs }: { title: string; breadcrumbs?: string[] }) {
  return (
    <header className="h-14 shrink-0 border-b border-border bg-background/80 backdrop-blur flex items-center gap-3 px-6">
      <div className="flex-1 min-w-0">
        {breadcrumbs && breadcrumbs.length > 0 && (
          <div className="text-[11px] text-muted-foreground font-medium tracking-wide">
            {breadcrumbs.join(" / ")}
          </div>
        )}
        <h1 className="text-[15px] font-semibold text-foreground leading-tight truncate">{title}</h1>
      </div>
      <div className="hidden lg:flex items-center relative">
        <Input placeholder="Zoek tickets, medewerkers, KB-artikelen…" className="h-9 w-[340px] pr-16 text-sm" />
        <kbd className="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-0.5 rounded border border-border bg-muted px-1.5 py-0.5 text-[10px] font-mono text-muted-foreground">
          <Command className="h-3 w-3" />K
        </kbd>
      </div>
      <Button variant="ghost" size="icon" className="h-9 w-9"><HelpCircle className="h-4 w-4" /></Button>
      <Button variant="ghost" size="icon" className="h-9 w-9 relative">
        <Bell className="h-4 w-4" />
        <span className="absolute top-2 right-2 h-1.5 w-1.5 rounded-full bg-destructive" />
      </Button>
      <Button size="sm" className="h-9 gap-1.5">
        <Plus className="h-3.5 w-3.5" /> Nieuw ticket
      </Button>
    </header>
  );
}
