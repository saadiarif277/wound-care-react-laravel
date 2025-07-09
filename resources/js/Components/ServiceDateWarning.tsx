import React from 'react';

interface ServiceDateWarningProps {
  expectedServiceDate: string;
}

const ServiceDateWarning: React.FC<ServiceDateWarningProps> = ({ expectedServiceDate }) => {
  if (!expectedServiceDate) return null;

  const today = new Date();
  const serviceDate = new Date(expectedServiceDate);
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);
  
  // Check if service date is within 24 hours
  const timeDiff = serviceDate.getTime() - today.getTime();
  const hoursDiff = timeDiff / (1000 * 60 * 60);
  const isWithin24Hours = hoursDiff > 0 && hoursDiff <= 24;
  
  // Check if service date is tomorrow and current time is after 2 PM CST
  const isTomorrow = serviceDate.toDateString() === tomorrow.toDateString();
  const currentHourCST = new Date().toLocaleString("en-US", {timeZone: "America/Chicago"});
  const currentTimeCST = new Date(currentHourCST);
  const cutoffTime = new Date(currentTimeCST);
  cutoffTime.setHours(14, 0, 0, 0); // 2 PM CST
  
  const isPastCutoff = currentTimeCST > cutoffTime;
  
  if (isTomorrow && isPastCutoff) {
    return (
      <div className="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
        <div className="flex items-start">
          <svg className="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
          </svg>
          <div>
            <h4 className="text-sm font-medium text-amber-800 dark:text-amber-300">
              Late Order Warning
            </h4>
            <p className="mt-1 text-sm text-amber-700 dark:text-amber-400">
              Service date is tomorrow and it's after 2 PM CST. Contact us via Support@mscwoundcare.com or call to see if possible.
            </p>
          </div>
        </div>
      </div>
    );
  } else if (isWithin24Hours && !isTomorrow) {
    return (
      <div className="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <div className="flex items-start">
          <svg className="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
          </svg>
          <div>
            <h4 className="text-sm font-medium text-blue-800 dark:text-blue-300">
              24-Hour Notice
            </h4>
            <p className="mt-1 text-sm text-blue-700 dark:text-blue-400">
              Service date is within 24 hours, contact Administration before placing.
            </p>
          </div>
        </div>
      </div>
    );
  }

  return null;
};

export default ServiceDateWarning; 