export type Tone = "neutral" | "success" | "warning" | "danger" | "info";

export const toneDot: Record<Tone, string> = {
  neutral: "bg-ink-subtle",
  success: "bg-success",
  warning: "bg-warning",
  danger: "bg-danger",
  info: "bg-info",
};

export const toneText: Record<Tone, string> = {
  neutral: "text-ink-muted",
  success: "text-success",
  warning: "text-warning",
  danger: "text-danger",
  info: "text-info",
};

export const toneSurface: Record<Tone, string> = {
  neutral: "bg-paper text-ink-muted",
  success: "bg-success-soft text-success",
  warning: "bg-warning-soft text-warning",
  danger: "bg-danger-soft text-danger",
  info: "bg-info-soft text-info",
};
