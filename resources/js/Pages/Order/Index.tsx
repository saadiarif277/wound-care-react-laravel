import React from 'react';
import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/Layouts/MainLayout';
import { FiPlus, FiFileText, FiCheckCircle, FiClock, FiDollarSign } from 'react-icons/fi';

// Dummy data types
interface Order {
  id: string;
  orderNumber: string;
  customerName: string;
  physicianName: string;
  orderDate: string;
  status: 'pending' | 'completed' | 'cancelled';
  itemsCount: number;
  totalAmount: number;
}

const OrdersPage = () => {
  // Dummy data for orders
  const orders: Order[] = [
    {
      id: 'ord-001',
      orderNumber: 'ORD-2023-001',
      customerName: 'Wound Care Clinic',
      physicianName: 'Dr. Smith',
      orderDate: '2023-06-15',
      status: 'completed',
      itemsCount: 5,
      totalAmount: 1245.75
    },
    {
      id: 'ord-002',
      orderNumber: 'ORD-2023-002',
      customerName: 'City Medical Center',
      physicianName: 'Dr. Johnson',
      orderDate: '2023-06-14',
      status: 'pending',
      itemsCount: 3,
      totalAmount: 845.50
    },
    {
      id: 'ord-003',
      orderNumber: 'ORD-2023-003',
      customerName: 'Rural Health Center',
      physicianName: 'Dr. Williams',
      orderDate: '2023-06-13',
      status: 'completed',
      itemsCount: 7,
      totalAmount: 2100.25
    },
    {
      id: 'ord-004',
      orderNumber: 'ORD-2023-004',
      customerName: 'Wound Care Clinic',
      physicianName: 'Dr. Brown',
      orderDate: '2023-06-12',
      status: 'cancelled',
      itemsCount: 2,
      totalAmount: 450.00
    },
    {
      id: 'ord-005',
      orderNumber: 'ORD-2023-005',
      customerName: 'City Medical Center',
      physicianName: 'Dr. Davis',
      orderDate: '2023-06-11',
      status: 'pending',
      itemsCount: 4,
      totalAmount: 980.00
    }
  ];

  // Calculate stats
  const totalOrders = orders.length;
  const completedOrders = orders.filter(o => o.status === 'completed').length;
  const pendingOrders = orders.filter(o => o.status === 'pending').length;
  const cancelledOrders = orders.filter(o => o.status === 'cancelled').length;
  const totalRevenue = orders.reduce((sum, order) => sum + order.totalAmount, 0);

  // Status badge component
  const StatusBadge = ({ status }: { status: string }) => {
    let bgColor = '';
    let textColor = '';

    switch(status) {
      case 'completed':
        bgColor = 'bg-green-100';
        textColor = 'text-green-800';
        break;
      case 'pending':
        bgColor = 'bg-amber-100';
        textColor = 'text-amber-800';
        break;
      case 'cancelled':
        bgColor = 'bg-red-100';
        textColor = 'text-red-800';
        break;
      default:
        bgColor = 'bg-gray-100';
        textColor = 'text-gray-800';
    }

    return (
      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${bgColor} ${textColor}`}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </span>
    );
  };

  return (
    <MainLayout>
      <Head title="Order Management" />

      <div className="space-y-6">
        {/* Header and Create Button */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">Order Management</h1>
            <p className="text-gray-500">
              View and manage all wound care product orders
            </p>
          </div>
          <Link href="/orders/create">
            <button className="flex items-center gap-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
              <FiPlus className="h-4 w-4" />
              Create New Order
            </button>
          </Link>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-4">
          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-medium text-gray-500">Total Orders</h3>
              <FiFileText className="h-4 w-4 text-gray-400" />
            </div>
            <p className="text-2xl font-bold mt-2">{totalOrders}</p>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-medium text-gray-500">Completed</h3>
              <FiCheckCircle className="h-4 w-4 text-green-500" />
            </div>
            <p className="text-2xl font-bold text-green-600 mt-2">{completedOrders}</p>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-medium text-gray-500">Pending</h3>
              <FiClock className="h-4 w-4 text-amber-500" />
            </div>
            <p className="text-2xl font-bold text-amber-600 mt-2">{pendingOrders}</p>
          </div>

          <div className="bg-white rounded-lg shadow p-6">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-medium text-gray-500">Total Revenue</h3>
              <FiDollarSign className="h-4 w-4 text-gray-400" />
            </div>
            <p className="text-2xl font-bold mt-2">${totalRevenue.toFixed(2)}</p>
          </div>
        </div>

        {/* Orders Table */}
        <div className="bg-white rounded-lg shadow overflow-hidden">
          <div className="p-6 border-b">
            <h2 className="text-lg font-semibold">Recent Orders</h2>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Order #
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Customer
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Physician
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Date
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Items
                  </th>
                  <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Amount
                  </th>
                  <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {orders.map((order) => (
                  <tr key={order.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {order.orderNumber}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {order.customerName}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {order.physicianName}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {order.orderDate}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <StatusBadge status={order.status} />
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {order.itemsCount}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                      ${order.totalAmount.toFixed(2)}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <Link href={`/orders/${order.id}`} className="text-indigo-600 hover:text-indigo-900">
                        View
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </MainLayout>
  );
};

export default OrdersPage;
