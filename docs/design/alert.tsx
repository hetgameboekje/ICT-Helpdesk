import { cn } from "@/lib/utils";
import { statusLabels, type Status } from "@/lib/mock-data";

const styles: Record<Status, string> = {
  open: "bg-status-open-bg text-status-open ring-status-open/20",
  wachtend: "bg-status-wachtend-bg text-status-wachtend ring-status-wachtend/20",
  behandeling: "bg-status-behandeling-bg text-status-behandeling ring-status-behandeling/20",
  opgelost: "bg-status-opgelost-bg text-status-opgelost ring-status-opgelost/20",
  gesloten: "bg-status-gesloten-bg text-status-gesloten ring-status-gesloten/25",
};

export function StatusBadge({ status, className }: { status: Status; className?: string }) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset",
        styles[status],
        className,
      )}
    >
      <span
        className="h-1.5 w-1.5 rounded-full"
        style={{ backgroundColor: `var(--status-${status})` }}
      />
      {statusLabels[status]}
    </span>
  );
}

const prioStyles = {
  laag: "text-muted-foreground",
  normaal: "text-foreground",
  hoog: "text-status-wachtend",
  kritiek: "text-destructive",
} as const;

export function PriorityPill({ prioriteit }: { prioriteit: keyof typeof prioStyles }) {
  return (
    <span className={cn("text-xs font-medium capitalize", prioStyles[prioriteit])}>
      {prioriteit === "kritiek" && "● "}
      {prioriteit}
    </span>
  );
}
