import { Toaster as Sonner, toast } from "sonner"
import { glassTheme } from '@/theme/glass-theme'

type ToasterProps = React.ComponentProps<typeof Sonner>

const Toaster = ({ ...props }: ToasterProps) => {
  return (
    <Sonner
      theme="dark"
      className="toaster group"
      toastOptions={{
        classNames: {
          toast: `group toast ${glassTheme.glass.base} ${glassTheme.shadows.glass} ${glassTheme.text.primary}`,
          description: glassTheme.text.secondary,
          actionButton: glassTheme.button.primary,
          cancelButton: glassTheme.button.secondary,
          error: `${glassTheme.status.error.bg} ${glassTheme.status.error.text} ${glassTheme.status.error.border}`,
          success: `${glassTheme.status.success.bg} ${glassTheme.status.success.text} ${glassTheme.status.success.border}`,
          warning: `${glassTheme.status.warning.bg} ${glassTheme.status.warning.text} ${glassTheme.status.warning.border}`,
          info: `${glassTheme.status.info.bg} ${glassTheme.status.info.text} ${glassTheme.status.info.border}`,
        },
      }}
      {...props}
    />
  )
}

export { Toaster, toast }