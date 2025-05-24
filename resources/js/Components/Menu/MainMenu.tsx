import MainMenuItem from '@/Components/Menu/MainMenuItem';
import { Building, CircleGauge, Printer, Users, Calculator, Receipt, Wallet } from 'lucide-react';

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
        text="Organizations"
        link="organizations"
        icon={<Building size={20} />}
      />

      <MainMenuItem
        text="Reports"
        link="reports"
        icon={<Printer size={20} />}
      />

      {/* Commission Management Section */}
      <div className="pt-4 mt-4 border-t border-indigo-800">
        <div className="px-3 mb-2 text-xs font-semibold text-indigo-200 uppercase">
          Commission Management
        </div>
        <MainMenuItem
          text="Commission Rules"
          link="commission-rules.index"
          icon={<Calculator size={20} />}
        />
        <MainMenuItem
          text="Commission Records"
          link="commission-records.index"
          icon={<Receipt size={20} />}
        />
        <MainMenuItem
          text="Commission Payouts"
          link="commission-payouts.index"
          icon={<Wallet size={20} />}
        />
      </div>
    </div>
  );
}
