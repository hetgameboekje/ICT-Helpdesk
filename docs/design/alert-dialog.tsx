import { Link, useRouterState } from "@tanstack/react-router";
import {
  Ticket,
  BookOpen,
  Sparkles,
  Lightbulb,
  MessageSquareQuote,
  Boxes,
  Users,
  Package,
  Laptop,
  Printer,
  ShieldAlert,
  PackageCheck,
  CalendarDays,
  UserCircle,
  Settings2,
  Wrench,
  Code2,
  HardDrive,
  Search,
  LayoutDashboard,
  Palette,
  ChevronRight,
} from "lucide-react";
import { useMemo, useState } from "react";
import { cn } from "@/lib/utils";
import { Input } from "@/components/ui/input";

type Item = { title: string; to: string; icon: React.ComponentType<{ className?: string }>; badge?: string };
type Group = { label: string; items: Item[] };

const groups: Group[] = [
  {
    label: "Werkplek",
    items: [
      { title: "Dashboard", to: "/", icon: LayoutDashboard },
      { title: "Agenda", to: "/modules/agenda", icon: CalendarDays },
      { title: "Account", to: "/modules/account", icon: UserCircle },
    ],
  },
  {
    label: "Support",
    items: [
      { title: "Tickets", to: "/tickets", icon: Ticket, badge: "42" },
      { title: "MailMind", to: "/mailmind", icon: Sparkles, badge: "8" },
      { title: "Kennisbank", to: "/modules/kennisbank", icon: BookOpen },
      { title: "Verbeterpunten", to: "/modules/verbeterpunt", icon: Lightbulb },
      { title: "Reflectie", to: "/modules/reflectie", icon: MessageSquareQuote },
    ],
  },
  {
    label: "Assets & Beheer",
    items: [
      { title: "Voorraad", to: "/modules/voorraad", icon: Boxes },
      { title: "Devices", to: "/modules/device", icon: Laptop },
      { title: "Printers", to: "/modules/printer", icon: Printer },
      { title: "Hardware-uitgaven", to: "/modules/hardware-uitgaven", icon: PackageCheck },
      { title: "Uitgifte", to: "/modules/uitgifte", icon: Package },
      { title: "CyberRisico", to: "/modules/cyberrisico", icon: ShieldAlert },
    ],
  },
  {
    label: "HR & CRM",
    items: [
      { title: "Medewerkers", to: "/modules/medewerker", icon: Users },
    ],
  },
  {
    label: "Systeem",
    items: [
      { title: "Beheer", to: "/modules/beheer", icon: Settings2 },
      { title: "Tools", to: "/modules/tools", icon: Wrench },
      { title: "Scripts", to: "/modules/script", icon: Code2 },
      { title: "Schijfgebruik", to: "/modules/schijfgebruik", icon: HardDrive },
      { title: "Design system", to: "/design-system", icon: Palette },
    ],
  },
];

export function AppSidebar() {
  const [q, setQ] = useState("");
  const pathname = useRouterState({ select: (s) => s.location.pathname });

  const filtered = useMemo(() => {
    if (!q.trim()) return groups;
    const term = q.toLowerCase();
    return groups
      .map((g) => ({ ...g, items: g.items.filter((i) => i.title.toLowerCase().includes(term)) }))
      .filter((g) => g.items.length > 0);
  }, [q]);

  return (
    <aside className="hidden md:flex md:w-[260px] shrink-0 flex-col bg-sidebar text-sidebar-foreground border-r border-sidebar-border">
      <div className="px-4 pt-5 pb-4 border-b border-sidebar-border">
        <Link to="/" className="flex items-center gap-2.5 group">
          <div className="grid place-items-center h-8 w-8 rounded-lg bg-sidebar-primary text-sidebar-primary-foreground font-bold text-sm">
            L
          </div>
          <div className="flex flex-col leading-tight">
            <span className="text-sm font-semibold text-white">Leen van Punt</span>
            <span className="text-[11px] text-sidebar-foreground/60 tracking-wide uppercase">Intranet</span>
          </div>
        </Link>
      </div>

      <div className="px-3 pt-3 pb-2">
        <div className="relative">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-sidebar-foreground/50" />
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Zoek module…"
            className="h-8 pl-8 bg-sidebar-accent border-transparent text-sidebar-foreground placeholder:text-sidebar-foreground/40 focus-visible:ring-1 focus-visible:ring-sidebar-ring text-sm"
          />
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto px-2 pb-4 space-y-4">
        {filtered.map((group) => (
          <div key={group.label}>
            <div className="px-3 pt-2 pb-1.5 text-[10.5px] font-semibold uppercase tracking-wider text-sidebar-foreground/45">
              {group.label}
            </div>
            <ul className="space-y-0.5">
              {group.items.map((item) => {
                const active =
                  item.to === "/"
                    ? pathname === "/"
                    : pathname === item.to || pathname.startsWith(item.to + "/");
                const Icon = item.icon;
                return (
                  <li key={item.to}>
                    <Link
                      to={item.to}
                      className={cn(
                        "flex items-center gap-2.5 px-3 py-1.5 rounded-md text-sm transition-colors",
                        active
                          ? "bg-sidebar-accent text-white font-medium"
                          : "text-sidebar-foreground/80 hover:bg-sidebar-accent/60 hover:text-white",
                      )}
                    >
                      <Icon className="h-4 w-4 shrink-0 opacity-80" />
                      <span className="flex-1 truncate">{item.title}</span>
                      {item.badge && (
                        <span className="text-[10.5px] font-mono px-1.5 py-0.5 rounded bg-sidebar-primary/15 text-sidebar-primary">
                          {item.badge}
                        </span>
                      )}
                      {active && <ChevronRight className="h-3 w-3 opacity-50" />}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>

      <div className="border-t border-sidebar-border p-3">
        <div className="flex items-center gap-2.5">
          <div className="grid place-items-center h-8 w-8 rounded-full bg-sidebar-accent text-xs font-semibold text-white">
            MB
          </div>
          <div className="flex-1 min-w-0 leading-tight">
            <div className="text-sm font-medium text-white truncate">Mila van den Berg</div>
            <div className="text-[11px] text-sidebar-foreground/60 truncate">Support · Keyuser</div>
          </div>
          <div className="h-2 w-2 rounded-full bg-primary" title="Online" />
        </div>
      </div>
    </aside>
  );
}
