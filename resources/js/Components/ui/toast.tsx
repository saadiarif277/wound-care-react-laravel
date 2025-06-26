// Simple wrapper around sonner toast so existing imports `@/components/ui/toast` work
import { toast as sonnerToast, Toaster as SonnerToaster } from "sonner";

export const toast = sonnerToast;
export const Toaster = SonnerToaster;

// Re-export Radix-based components from the original Shadcn/GhostAiUi implementation so existing code that
// imports them from "@/components/ui/toast" continues to compile.
export {
  ToastProvider,
  Toast,
  ToastAction,
  ToastClose,
  ToastDescription,
  ToastTitle,
  ToastViewport,
} from "@/Components/GhostAiUi/ui/toast";
