
import React from 'react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../ui/table';
import { Badge } from '../ui/badge';
import { AdminOrderData, OrderStatus } from '../types/adminTypes';

interface OrderTableProps {
  orders: AdminOrderData[];
  onRowClick: (order: AdminOrderData) => void;
}

const getStatusColor = (status: OrderStatus): string => {
  switch (status) {
    case 'Pending IVR': return 'bg-gray-100 text-gray-800';
    case 'IVR Sent': return 'bg-blue-100 text-blue-800';
    case 'IVR Verified': return 'bg-purple-100 text-purple-800';
    case 'Approved': return 'bg-green-100 text-green-800';
    case 'Denied': return 'bg-red-100 text-red-800';
    case 'Send Back': return 'bg-orange-100 text-orange-800';
    case 'Submitted to Manufacturer': return 'bg-emerald-100 text-emerald-800';
    case 'Confirmed & Shipped': return 'bg-teal-100 text-teal-800';
    default: return 'bg-gray-100 text-gray-800';
  }
};

export const OrderTable: React.FC<OrderTableProps> = ({ orders, onRowClick }) => {
  return (
    <div className="border rounded-lg">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Patient ID</TableHead>
            <TableHead>Order ID</TableHead>
            <TableHead>Product Name</TableHead>
            <TableHead>Provider</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Request Date</TableHead>
            <TableHead>Manufacturer</TableHead>
            <TableHead>Action Required</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {orders.map((order) => (
            <TableRow 
              key={order.orderNumber}
              className="cursor-pointer hover:bg-muted/50"
              onClick={() => onRowClick(order)}
            >
              <TableCell className="font-medium">{order.patientIdentifier}</TableCell>
              <TableCell>{order.orderNumber}</TableCell>
              <TableCell>{order.productName}</TableCell>
              <TableCell>{order.providerName}</TableCell>
              <TableCell>
                <Badge className={getStatusColor(order.orderStatus)}>
                  {order.orderStatus}
                </Badge>
              </TableCell>
              <TableCell>{order.orderRequestDate}</TableCell>
              <TableCell>{order.manufacturerName}</TableCell>
              <TableCell>
                {order.actionRequired ? (
                  <span className="flex items-center gap-1 text-red-600">
                    <span className="w-2 h-2 bg-red-500 rounded-full"></span>
                    Yes
                  </span>
                ) : (
                  <span className="text-gray-500">No</span>
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
};
