import * as React from "react";
import * as RadixCollapsible from "@radix-ui/react-collapsible";
import { cn } from "@/lib/utils";

export const Collapsible = RadixCollapsible.Root;
export const CollapsibleTrigger = RadixCollapsible.Trigger;
export const CollapsibleContent = React.forwardRef<
  React.ElementRef<typeof RadixCollapsible.Content>,
  React.ComponentPropsWithoutRef<typeof RadixCollapsible.Content>
>(({ className, ...props }, ref) => (
  <RadixCollapsible.Content
    ref={ref}
    className={cn("overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down", className)}
    {...props}
  />
));
CollapsibleContent.displayName = RadixCollapsible.Content.displayName;
