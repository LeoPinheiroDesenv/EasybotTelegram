import React from 'react';
import { Link } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';

function Button({
  text,
  type = 'button',
  isLoading,
  disabled,
  className = 'btn-primary',
  children,
  icon,
  loadingClass = 'unset-classname',
  iconPosition = 'left',
  iconClass = 'text-[20px]',
  link,
  onClick,
  div,
  width,
  rotate,
  hFlip,
  vFlip,
}) {
  const baseClasses = `btn ${isLoading ? 'pointer-events-none' : ''} ${
    disabled ? 'opacity-60 cursor-not-allowed' : ''
  } ${className}`;

  const iconElement = icon && (
    <span
      className={`
        ${iconPosition === 'right' ? 'order-1 ml-2' : ''}
        ${text && iconPosition === 'left' ? 'mr-2' : ''}
        ${iconClass}
      `}
    >
      <FontAwesomeIcon icon={icon} />
    </span>
  );

  const loadingSpinner = (
    <>
      <svg
        className={`animate-spin -ml-1 mr-3 h-5 w-5 ${loadingClass}`}
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
      >
        <circle
          className="opacity-25"
          cx="12"
          cy="12"
          r="10"
          stroke="currentColor"
          strokeWidth="4"
        ></circle>
        <path
          className="opacity-75"
          fill="currentColor"
          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
        ></path>
      </svg>
      Loading ...
    </>
  );

  const content = (
    <>
      {children && !isLoading && children}
      {!children && !isLoading && (
        <span className="flex items-center">
          {iconPosition === 'left' && iconElement}
          {text && <span>{text}</span>}
          {iconPosition === 'right' && iconElement}
        </span>
      )}
      {isLoading && loadingSpinner}
    </>
  );

  if (link) {
    return (
      <Link to={link} className={baseClasses}>
        {content}
      </Link>
    );
  }

  if (div) {
    return (
      <div onClick={onClick} className={baseClasses}>
        {content}
      </div>
    );
  }

  return (
    <button type={type} onClick={onClick} className={baseClasses} disabled={disabled || isLoading}>
      {content}
    </button>
  );
}

export default Button;

