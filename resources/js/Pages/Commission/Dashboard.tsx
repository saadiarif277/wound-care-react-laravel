import React, { useState } from "react";
import {
  ArrowUpRight,
  ArrowDownRight,
  DollarSign,
  Users,
  BarChart3,
  LineChart,
  Calendar,
  Download,
  Filter,
  Search,
  ChevronDown,
  CheckCircle2,
  Circle,
  Target,
} from "lucide-react";
import { Button } from "@/Components/Button";
import { Card } from "@/Components/Card";
import { Input } from "@/Components/Input";
import MainLayout from "@/Layouts/MainLayout";

// Utility function to merge class names
function cn(...classes: (string | undefined)[]): string {
  return classes.filter(Boolean).join(' ');
}

interface CommissionStat {
  title: string;
  value: string;
  change: string;
  isPositive: boolean;
  icon: React.ReactNode;
}

interface SalesRep {
  id: string;
  name: string;
  avatar: string;
  sales: number;
  commission: number;
  patients: number;
  performance: number;
}

interface CommissionPayment {
  id: string;
  amount: number;
  date: string;
  status: "pending" | "processing" | "completed";
  salesRep: string;
}

interface CommissionTrackingProps extends React.HTMLAttributes<HTMLDivElement> {
  steps: {
    name: string;
    timestamp: string;
    isCompleted: boolean;
  }[];
}

function Badge({ children, variant = "default", className = "" }: {
  children: React.ReactNode;
  variant?: "default" | "success" | "warning" | "danger" | "secondary" | "outline" | "primary";
  className?: string;
}) {
  const variants = {
    default: "text-white text-xs font-medium px-2.5 py-0.5 rounded-full",
    success: "text-white text-xs font-medium px-2.5 py-0.5 rounded-full",
    warning: "bg-yellow-100 text-yellow-800",
    danger: "text-white text-xs font-medium px-2.5 py-0.5 rounded-full",
    primary: "text-white text-xs font-medium px-2.5 py-0.5 rounded-full",
    secondary: "bg-gray-100 text-gray-600",
    outline: "border text-gray-700 bg-transparent text-xs font-medium px-2.5 py-0.5 rounded-full",
  };

  const getBackgroundColor = () => {
    switch(variant) {
      case "default":
      case "primary":
      case "success":
        return "#1822cf";
      case "danger":
        return "#cb0909";
      case "outline":
        return "transparent";
      default:
        return undefined;
    }
  };

  const getBorderColor = () => {
    return variant === "outline" ? "#1822cf" : undefined;
  };

  return (
    <span
      className={`inline-flex items-center ${variants[variant]} ${className}`}
      style={{
        backgroundColor: getBackgroundColor(),
        borderColor: getBorderColor(),
        borderWidth: variant === "outline" ? "1px" : undefined,
      }}
    >
      {children}
    </span>
  );
}

function Tabs({ children, defaultValue, className = "" }: {
  children: React.ReactNode;
  defaultValue: string;
  className?: string;
}) {
  const [activeTab, setActiveTab] = useState(defaultValue);

  React.useEffect(() => {
    // Initialize the active tab styling on mount
    const tabsContainer = document.querySelector('[data-active-tab]');
    if (tabsContainer) {
      const allTriggers = tabsContainer.querySelectorAll('[data-value]');
      allTriggers.forEach(trigger => {
        const triggerValue = trigger.getAttribute('data-value');
        if (triggerValue === defaultValue) {
          trigger.setAttribute('data-state', 'active');
          (trigger as HTMLElement).style.color = '#1822cf';
          (trigger as HTMLElement).style.backgroundColor = 'white';
        } else {
          trigger.setAttribute('data-state', 'inactive');
          (trigger as HTMLElement).style.color = '#6b7280';
          (trigger as HTMLElement).style.backgroundColor = 'transparent';
        }
      });

      // Show the default tab content
      const allContents = tabsContainer.querySelectorAll('[data-tab-content]');
      allContents.forEach(content => {
        const contentValue = content.getAttribute('data-tab-content');
        if (contentValue === defaultValue) {
          content.classList.remove('hidden');
        } else {
          content.classList.add('hidden');
        }
      });
    }
  }, [defaultValue]);

  return (
    <div className={className} data-active-tab={activeTab} data-set-tab={setActiveTab}>
      {children}
    </div>
  );
}

function TabsList({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return (
    <div className={`inline-flex h-10 items-center justify-center rounded-md bg-gray-100 p-1 text-gray-500 ${className}`}>
      {children}
    </div>
  );
}

function TabsTrigger({ children, value, className = "" }: {
  children: React.ReactNode;
  value: string;
  className?: string;
}) {
  return (
    <button
      className={`inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-white transition-all focus-visible:outline-none focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-white data-[state=active]:shadow-sm ${className}`}
      style={{
        color: 'inherit',
      }}
      data-value={value}
      onClick={(e) => {
        const tabsContainer = e.currentTarget.closest('[data-set-tab]');
        if (tabsContainer) {
          const setTab = tabsContainer.getAttribute('data-set-tab');
          if (setTab) {
            // This is a simplified implementation - in a real app you'd use proper state management
            tabsContainer.setAttribute('data-active-tab', value);
            // Hide all tab contents
            const allContents = tabsContainer.querySelectorAll('[data-tab-content]');
            allContents.forEach(content => content.classList.add('hidden'));
            // Show the selected tab content
            const selectedContent = tabsContainer.querySelector(`[data-tab-content="${value}"]`);
            if (selectedContent) {
              selectedContent.classList.remove('hidden');
            }
            // Update trigger states
            const allTriggers = tabsContainer.querySelectorAll('[data-value]');
            allTriggers.forEach(trigger => {
              trigger.setAttribute('data-state', 'inactive');
              (trigger as HTMLElement).style.color = '#6b7280';
              (trigger as HTMLElement).style.backgroundColor = 'transparent';
            });
            e.currentTarget.setAttribute('data-state', 'active');
            e.currentTarget.style.color = '#1822cf';
            e.currentTarget.style.backgroundColor = 'white';
          }
        }
      }}
    >
      {children}
    </button>
  );
}

function TabsContent({ children, value, className = "" }: {
  children: React.ReactNode;
  value: string;
  className?: string;
}) {
  return (
    <div
      className={`mt-2 ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2 ${className}`}
      data-tab-content={value}
    >
      {children}
    </div>
  );
}

// Table components
function Table({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return (
    <div className={`w-full overflow-auto ${className}`}>
      <table className="w-full caption-bottom text-sm">
        {children}
      </table>
    </div>
  );
}

function TableHeader({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return <thead className={`[&_tr]:border-b ${className}`}>{children}</thead>;
}

function TableRow({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return <tr className={`border-b transition-colors hover:bg-gray-50 ${className}`}>{children}</tr>;
}

function TableHead({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return (
    <th className={`h-12 px-4 text-left align-middle font-medium text-gray-500 ${className}`}>
      {children}
    </th>
  );
}

function TableBody({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return <tbody className={`[&_tr:last-child]:border-0 ${className}`}>{children}</tbody>;
}

function TableCell({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return <td className={`p-4 align-middle ${className}`}>{children}</td>;
}

// Select components
function Select({ children, defaultValue, className = "" }: { children: React.ReactNode; defaultValue?: string; className?: string }) {
  return (
    <div className={`relative ${className}`}>
      <select className="flex h-10 w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50">
        {children}
      </select>
    </div>
  );
}

function SelectTrigger({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return (
    <button
      className={`flex h-10 w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
      style={{'--tw-ring-color': '#1822cf'} as React.CSSProperties}
    >
      {children}
    </button>
  );
}

function SelectValue({ placeholder }: { placeholder?: string }) {
  return <span className="text-gray-500">{placeholder}</span>;
}

function SelectContent({ children, className = "" }: { children: React.ReactNode; className?: string }) {
  return (
    <div className={`relative z-50 min-w-[8rem] overflow-hidden rounded-md border bg-white text-gray-900 shadow-md ${className}`}>
      {children}
    </div>
  );
}

function SelectItem({ children, value, className = "" }: { children: React.ReactNode; value: string; className?: string }) {
  return (
    <div className={`relative flex cursor-pointer select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none hover:bg-gray-100 focus:bg-gray-100 ${className}`}>
      {children}
    </div>
  );
}

const CommissionTracking = React.forwardRef<HTMLDivElement, CommissionTrackingProps>(
  ({ steps = [], className, ...props }, ref) => {
    return (
      <div ref={ref} className={`w-full ${className}`} {...props}>
        {steps.length > 0 ? (
          <div>
            {steps.map((step, index) => (
              <div key={index} className="flex">
                <div className="flex flex-col items-center">
                  {step.isCompleted ? (
                    <CheckCircle2 className="h-6 w-6 shrink-0" style={{color: '#1822cf'}} />
                  ) : (
                    <Circle className="h-6 w-6 shrink-0 text-gray-400" />
                  )}
                  {index < steps.length - 1 && (
                    <div
                      className="w-[1.5px] grow"
                      style={{backgroundColor: steps[index + 1].isCompleted ? '#1822cf' : '#9ca3af'}}
                    />
                  )}
                </div>
                <div className="ml-3 pb-6">
                  <p className="text-sm font-medium">{step.name}</p>
                  <p className="text-sm text-gray-500">
                    {step.timestamp}
                  </p>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-sm text-gray-600">
            This commission has no tracking information.
          </p>
        )}
      </div>
    );
  }
);
CommissionTracking.displayName = "CommissionTracking";

function CommissionDashboard() {
  const stats: CommissionStat[] = [
    {
      title: "Total Commission",
      value: "$124,500",
      change: "+12.5%",
      isPositive: true,
      icon: <DollarSign className="w-4 h-4" style={{color: '#1822cf'}} />,
    },
    {
      title: "Active Sales Reps",
      value: "42",
      change: "+8.1%",
      isPositive: true,
      icon: <Users className="w-4 h-4" style={{color: '#1822cf'}} />,
    },
    {
      title: "Avg. Commission Rate",
      value: "8.2%",
      change: "-0.5%",
      isPositive: false,
      icon: <BarChart3 className="w-4 h-4" style={{color: '#cb0909'}} />,
    },
    {
      title: "Patient Referrals",
      value: "1,245",
      change: "+15.3%",
      isPositive: true,
      icon: <LineChart className="w-4 h-4" style={{color: '#1822cf'}} />,
    },
  ];

  const salesReps: SalesRep[] = [
    {
      id: "1",
      name: "Sarah Johnson",
      avatar: "SJ",
      sales: 45600,
      commission: 4560,
      patients: 32,
      performance: 115,
    },
    {
      id: "2",
      name: "Michael Chen",
      avatar: "MC",
      sales: 38200,
      commission: 3820,
      patients: 27,
      performance: 108,
    },
    {
      id: "3",
      name: "Jessica Williams",
      avatar: "JW",
      sales: 52300,
      commission: 5230,
      patients: 41,
      performance: 124,
    },
    {
      id: "4",
      name: "David Rodriguez",
      avatar: "DR",
      sales: 31500,
      commission: 3150,
      patients: 22,
      performance: 94,
    },
    {
      id: "5",
      name: "Emily Thompson",
      avatar: "ET",
      sales: 48900,
      commission: 4890,
      patients: 36,
      performance: 118,
    },
  ];

  const commissionPayments: CommissionPayment[] = [
    {
      id: "PAY-2023-001",
      amount: 4560,
      date: "2023-10-15",
      status: "completed",
      salesRep: "Sarah Johnson",
    },
    {
      id: "PAY-2023-002",
      amount: 3820,
      date: "2023-10-15",
      status: "completed",
      salesRep: "Michael Chen",
    },
    {
      id: "PAY-2023-003",
      amount: 5230,
      date: "2023-10-15",
      status: "completed",
      salesRep: "Jessica Williams",
    },
    {
      id: "PAY-2023-004",
      amount: 3150,
      date: "2023-11-15",
      status: "processing",
      salesRep: "David Rodriguez",
    },
    {
      id: "PAY-2023-005",
      amount: 4890,
      date: "2023-11-15",
      status: "pending",
      salesRep: "Emily Thompson",
    },
  ];

  const commissionSteps = [
    {
      name: "Commission Calculated",
      timestamp: "2023-10-01",
      isCompleted: true,
    },
    {
      name: "Manager Approval",
      timestamp: "2023-10-05",
      isCompleted: true,
    },
    {
      name: "Finance Review",
      timestamp: "2023-10-10",
      isCompleted: true,
    },
    {
      name: "Payment Processing",
      timestamp: "2023-10-14",
      isCompleted: true,
    },
    {
      name: "Payment Completed",
      timestamp: "2023-10-15",
      isCompleted: true,
    },
  ];

  return (
    <div className="min-h-screen bg-gray-50 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto py-4 sm:py-6 lg:py-8">
        <div className="flex flex-col space-y-4 sm:space-y-6 lg:space-y-8">
          {/* Header - Mobile Optimized */}
          <div className="flex flex-col space-y-4 sm:space-y-0 sm:flex-row sm:justify-between sm:items-start">
            <div className="flex-1">
              <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">Commission Dashboard</h1>
              <p className="mt-1 sm:mt-2 text-sm sm:text-base text-gray-600">
                Monitor sales performance and commission payouts
              </p>
            </div>
            <div className="flex flex-col sm:flex-row gap-3 sm:gap-4">
              <Button variant="secondary" className="w-full sm:w-auto justify-center gap-2">
                <Calendar className="w-4 h-4" />
                Q4 2023
                <ChevronDown className="w-4 h-4" />
              </Button>
              <Button variant="secondary" className="w-full sm:w-auto justify-center gap-2">
                <Download className="w-4 h-4" />
                Export
              </Button>
            </div>
          </div>

          {/* Stats Grid - Mobile First */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 lg:gap-6">
            {stats.map((stat, index) => (
              <Card key={index} className="p-4 sm:p-6 shadow-sm sm:shadow-lg border border-gray-100">
                <div className="flex flex-col gap-3 sm:gap-4">
                  <div className="flex justify-between items-center">
                    <span className="text-xs sm:text-sm text-gray-600 font-medium">{stat.title}</span>
                    {stat.icon}
                  </div>
                  <div className="flex flex-col gap-1 sm:gap-2">
                    <span className="text-xl sm:text-2xl font-semibold text-gray-900">{stat.value}</span>
                    <div className="flex items-center gap-1">
                      {stat.isPositive ? (
                        <ArrowUpRight className="w-3 h-3 sm:w-4 sm:h-4" style={{color: '#1822cf'}} />
                      ) : (
                        <ArrowDownRight className="w-3 h-3 sm:w-4 sm:h-4" style={{color: '#cb0909'}} />
                      )}
                      <span className={cn(
                        "text-xs sm:text-sm font-medium"
                      )} style={{color: stat.isPositive ? '#1822cf' : '#cb0909'}}>
                        {stat.change} from last period
                      </span>
                    </div>
                  </div>
                </div>
              </Card>
            ))}
          </div>

          {/* Tabs Section - Mobile Optimized */}
          <Tabs defaultValue="sales-reps">
            {/* Mobile-friendly Tab List */}
            <div className="w-full overflow-x-auto">
              <TabsList className="mb-4 w-full sm:w-auto">
                <TabsTrigger value="sales-reps" className="flex-1 sm:flex-none">Sales Reps</TabsTrigger>
                <TabsTrigger value="payouts" className="flex-1 sm:flex-none">Payouts</TabsTrigger>
                <TabsTrigger value="tracking" className="flex-1 sm:flex-none">Tracking</TabsTrigger>
              </TabsList>
            </div>

            <TabsContent value="sales-reps">
              <Card className="shadow-sm sm:shadow-lg border border-gray-100">
                {/* Mobile-optimized header */}
                <div className="p-4 sm:p-6 border-b border-gray-200 space-y-4">
                  <h3 className="text-lg sm:text-xl font-semibold text-gray-900">Sales Representatives Performance</h3>
                  <div className="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                    <div className="relative flex-1 sm:max-w-sm">
                      <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                      <input
                        type="search"
                        placeholder="Search reps..."
                        className="pl-8 w-full h-10 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2"
                        style={{'--tw-ring-color': '#1822cf'} as React.CSSProperties}
                      />
                    </div>
                    <Button variant="secondary" className="w-full sm:w-auto justify-center">
                      <Filter className="h-4 w-4 mr-2" />
                      Filter
                    </Button>
                  </div>
                </div>

                {/* Mobile-friendly Sales Reps Display */}
                <div className="block sm:hidden">
                  {/* Mobile Cards View */}
                  <div className="divide-y divide-gray-200">
                    {salesReps.map((rep) => (
                      <div key={rep.id} className="p-4 hover:bg-gray-50 transition-colors">
                        <div className="flex items-center justify-between mb-3">
                          <div className="flex items-center space-x-3">
                            <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                              <span className="text-white text-xs font-medium">{rep.avatar}</span>
                            </div>
                            <h4 className="text-sm font-medium text-gray-900">{rep.name}</h4>
                          </div>
                          <div className="flex items-center space-x-1">
                            <span className="text-sm font-medium">{rep.performance}%</span>
                            {rep.performance >= 100 ? (
                              <ArrowUpRight className="h-4 w-4" style={{color: '#1822cf'}} />
                            ) : (
                              <ArrowDownRight className="h-4 w-4" style={{color: '#cb0909'}} />
                            )}
                          </div>
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                          <div className="bg-gray-50 rounded-lg p-2">
                            <p className="text-xs text-gray-500">Sales</p>
                            <p className="text-sm font-medium">${rep.sales.toLocaleString()}</p>
                          </div>
                          <div className="bg-gray-50 rounded-lg p-2">
                            <p className="text-xs text-gray-500">Commission</p>
                            <p className="text-sm font-medium">${rep.commission.toLocaleString()}</p>
                          </div>
                          <div className="bg-gray-50 rounded-lg p-2">
                            <p className="text-xs text-gray-500">Patients</p>
                            <p className="text-sm font-medium">{rep.patients}</p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Desktop Table View */}
                <div className="hidden sm:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Sales Rep</TableHead>
                        <TableHead className="text-right">Total Sales</TableHead>
                        <TableHead className="text-right">Commission</TableHead>
                        <TableHead className="text-right">Patients</TableHead>
                        <TableHead className="text-right">Performance</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {salesReps.map((rep) => (
                        <TableRow key={rep.id}>
                          <TableCell className="font-medium">
                            <div className="flex items-center space-x-3">
                              <div className="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                <span className="text-white text-xs font-medium">{rep.avatar}</span>
                              </div>
                              <span>{rep.name}</span>
                            </div>
                          </TableCell>
                          <TableCell className="text-right">${rep.sales.toLocaleString()}</TableCell>
                          <TableCell className="text-right">${rep.commission.toLocaleString()}</TableCell>
                          <TableCell className="text-right">{rep.patients}</TableCell>
                          <TableCell className="text-right">
                            <div className="flex items-center justify-end gap-2">
                              <span>{rep.performance}%</span>
                              {rep.performance >= 100 ? (
                                <ArrowUpRight className="h-4 w-4" style={{color: '#1822cf'}} />
                              ) : (
                                <ArrowDownRight className="h-4 w-4" style={{color: '#cb0909'}} />
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </Card>
            </TabsContent>

            <TabsContent value="payouts">
              <Card className="shadow-sm sm:shadow-lg border border-gray-100">
                {/* Mobile-optimized header */}
                <div className="p-4 sm:p-6 border-b border-gray-200 space-y-4">
                  <h3 className="text-lg sm:text-xl font-semibold text-gray-900">Commission Payouts</h3>
                  <div className="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                    <Select defaultValue="all" className="w-full sm:w-[180px]">
                      <SelectTrigger>
                        <SelectValue placeholder="Filter by status" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="processing">Processing</SelectItem>
                        <SelectItem value="completed">Completed</SelectItem>
                      </SelectContent>
                    </Select>
                    <Button variant="secondary" className="w-full sm:w-auto justify-center">
                      <Download className="h-4 w-4 mr-2" />
                      Export
                    </Button>
                  </div>
                </div>

                {/* Mobile-friendly Payouts Display */}
                <div className="block sm:hidden">
                  {/* Mobile Cards View */}
                  <div className="divide-y divide-gray-200">
                    {commissionPayments.map((payment) => (
                      <div key={payment.id} className="p-4 hover:bg-gray-50 transition-colors">
                        <div className="flex items-center justify-between mb-3">
                          <div>
                            <h4 className="text-sm font-medium text-gray-900">{payment.salesRep}</h4>
                            <p className="text-xs text-gray-500">{payment.id}</p>
                          </div>
                          <Badge variant={
                            payment.status === "completed" ? "default" :
                            payment.status === "processing" ? "secondary" : "outline"
                          }>
                            {payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                          </Badge>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                          <div className="bg-gray-50 rounded-lg p-2">
                            <p className="text-xs text-gray-500">Amount</p>
                            <p className="text-lg font-bold text-green-600">${payment.amount.toLocaleString()}</p>
                          </div>
                          <div className="bg-gray-50 rounded-lg p-2">
                            <p className="text-xs text-gray-500">Date</p>
                            <p className="text-sm font-medium">{new Date(payment.date).toLocaleDateString()}</p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Desktop Table View */}
                <div className="hidden sm:block">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Payment ID</TableHead>
                        <TableHead>Sales Rep</TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Status</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {commissionPayments.map((payment) => (
                        <TableRow key={payment.id}>
                          <TableCell className="font-medium">{payment.id}</TableCell>
                          <TableCell>{payment.salesRep}</TableCell>
                          <TableCell className="text-right">${payment.amount.toLocaleString()}</TableCell>
                          <TableCell>{new Date(payment.date).toLocaleDateString()}</TableCell>
                          <TableCell>
                            <Badge variant={
                              payment.status === "completed" ? "default" :
                              payment.status === "processing" ? "secondary" : "outline"
                            }>
                              {payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}
                            </Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </Card>
            </TabsContent>

            <TabsContent value="tracking">
              <Card className="p-4 sm:p-6 shadow-sm sm:shadow-lg border border-gray-100">
                <div className="flex flex-col gap-4 sm:gap-6">
                  <div>
                    <h3 className="text-lg sm:text-xl font-semibold text-gray-900 mb-2">Commission Payment Tracking</h3>
                    <p className="text-sm sm:text-base text-gray-600">Track the status of commission payments through the approval and payment process</p>
                  </div>

                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
                    <div>
                      <h4 className="text-sm font-medium mb-4 text-gray-900">Payment Process for October 2023</h4>
                      <CommissionTracking steps={commissionSteps} />
                    </div>

                    <Card className="p-4 border border-gray-200">
                      <div className="flex flex-col gap-4">
                        <h4 className="text-sm font-medium text-gray-900">Payment Summary</h4>
                        <div className="space-y-3">
                          <div className="flex justify-between">
                            <span className="text-sm text-gray-600">Total Reps</span>
                            <span className="text-sm font-medium">5</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-sm text-gray-600">Total Amount</span>
                            <span className="text-sm font-medium">$21,650</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-sm text-gray-600">Payment Date</span>
                            <span className="text-sm font-medium">October 15, 2023</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-sm text-gray-600">Status</span>
                            <Badge>Completed</Badge>
                          </div>
                        </div>
                        <Button className="w-full mt-2">View Details</Button>
                      </div>
                    </Card>
                  </div>
                </div>
              </Card>
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </div>
  );
}

export default function CommissionDashboardPage() {
  return (
    <MainLayout title="Commission Dashboard">
      <CommissionDashboard />
    </MainLayout>
  );
}
