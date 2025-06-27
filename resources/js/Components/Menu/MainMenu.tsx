import MainMenuItem from '@/Components/Menu/MainMenuItem';
import { Building, CircleGauge, Printer, Users, Calculator, Receipt, Wallet, ShoppingCart, Globe, DollarSign } from 'lucide-react';

interface MainMenuProps {
  className?: string;
}

export default function MainMenu({ className }: MainMenuProps) {
  return (
    <div className={className}>
      <MainMenuItem
        text="Dashboard"
        link="dashboard"
        icon={<CircleGauge size={20} />}
      />

      <MainMenuItem
        text="Order Management"
        link="orders.management"
        icon={<ShoppingCart size={20} />}
      />

      <MainMenuItem
        text="Organizations & Analytics"
        link="admin.organizations.index"
        icon={<Globe size={20} />}
      />

      <MainMenuItem
        text="Sales Management"
        link="commission.management"
        icon={<DollarSign size={20} />}
      />

      <MainMenuItem
        text="Reports"
        link="reports"
        icon={<Printer size={20} />}
      />
    </div>
  );
}
