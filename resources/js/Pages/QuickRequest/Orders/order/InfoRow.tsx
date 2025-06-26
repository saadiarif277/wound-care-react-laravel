
import React from 'react';

interface InfoRowProps {
  label: string;
  value: string;
  icon?: React.ComponentType<{ className?: string }>;
}

export const InfoRow: React.FC<InfoRowProps> = ({ label, value, icon: Icon }) => (
  <div className="flex justify-between items-center py-2">
    <div className="flex items-center gap-2">
      {Icon && <Icon className="h-4 w-4 text-muted-foreground" />}
      <span className="font-medium text-sm">{label}:</span>
    </div>
    <span className="text-sm text-muted-foreground">{value}</span>
  </div>
);
