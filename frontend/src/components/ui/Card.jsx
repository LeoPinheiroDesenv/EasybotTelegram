import React from 'react';

const Card = ({
  children,
  title,
  subtitle,
  headerslot,
  className = '',
  bodyClass = 'px-5 py-4',
  noborder,
  titleClass = '',
  headerClass = '',
  footer,
  footerClass = '',
}) => {
  return (
    <div className={`card ${className}`}>
      {(title || subtitle || headerslot) && (
        <header
          className={`card-header ${noborder ? 'no-border' : ''} ${headerClass}`}
        >
          <div>
            {title && <div className={`card-title ${titleClass}`}>{title}</div>}
            {subtitle && <div className="card-subtitle">{subtitle}</div>}
          </div>
          {headerslot && <div className="card-header-slot">{headerslot}</div>}
        </header>
      )}
      <main className={`card-body ${bodyClass}`}>{children}</main>
      {footer && <footer className={`card-footer ${footerClass}`}>{footer}</footer>}
    </div>
  );
};

export default Card;

