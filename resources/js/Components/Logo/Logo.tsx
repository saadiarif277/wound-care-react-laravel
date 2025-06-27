import React from 'react';

interface LogoProps extends React.ImgHTMLAttributes<HTMLImageElement> {
  //
}

export default function Logo(props: LogoProps) {
  return (
    <img
      src="/MSC-logo.png"
      alt="MSC Wound Care"
      {...props}
    />
  );
}
