
import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Pages/QuickRequest/Orders/ui/card';
import { Collapsible, CollapsibleTrigger, CollapsibleContent } from '@/Pages/QuickRequest/Orders/ui/collapsible';
import { ChevronDown, ChevronUp } from 'lucide-react';

interface SectionCardProps {
  title: string;
  icon: React.ComponentType<{ className?: string }>;
  sectionKey: string;
  children: React.ReactNode;
  isOpen: boolean;
  onToggle: (section: string) => void;
}

export const SectionCard: React.FC<SectionCardProps> = ({ 
  title, 
  icon: Icon, 
  sectionKey, 
  children,
  isOpen,
  onToggle
}) => (
  <Card className="mb-4">
    <Collapsible 
      open={isOpen} 
      onOpenChange={() => onToggle(sectionKey)}
    >
      <CollapsibleTrigger asChild>
        <CardHeader className="cursor-pointer hover:bg-muted/50 transition-colors">
          <CardTitle className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <Icon className="h-5 w-5 text-primary" />
              {title}
            </div>
            <div className="flex items-center gap-2">
              {isOpen ? <ChevronUp /> : <ChevronDown />}
            </div>
          </CardTitle>
        </CardHeader>
      </CollapsibleTrigger>
      <CollapsibleContent>
        <CardContent>{children}</CardContent>
      </CollapsibleContent>
    </Collapsible>
  </Card>
);
