import React, { useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEye, faEyeSlash, faInfoCircle, faCheckCircle } from '@fortawesome/free-solid-svg-icons';

const Textinput = ({
  type,
  label,
  placeholder,
  classLabel = 'form-label',
  className = '',
  classGroup = '',
  register,
  name,
  readonly,
  error,
  icon,
  disabled,
  id,
  horizontal,
  validate,
  description,
  hasicon,
  onChange,
  defaultValue,
  ...rest
}) => {
  const [open, setOpen] = useState(false);
  const handleOpen = () => {
    setOpen(!open);
  };

  const inputType = type === 'password' && open ? 'text' : type;

  return (
    <div
      className={`textfiled-wrapper ${error ? 'is-error' : ''} ${
        validate ? 'is-valid' : ''
      } ${horizontal ? 'flex' : ''} ${classGroup}`}
    >
      {label && (
        <label
          htmlFor={id}
          className={`block capitalize ${classLabel} ${
            horizontal ? 'flex-0 mr-6 md:w-[100px] w-[60px] break-words' : ''
          }`}
        >
          {label}
        </label>
      )}
      <div className={`relative ${horizontal ? 'flex-1' : ''}`}>
        {name ? (
          <input
            type={inputType}
            {...register(name)}
            {...rest}
            className={`${
              error ? 'is-error' : ''
            } text-control py-[10px] ${className}`}
            placeholder={placeholder}
            readOnly={readonly}
            disabled={disabled}
            id={id}
            onChange={onChange}
            defaultValue={defaultValue}
          />
        ) : (
          <input
            type={inputType}
            className={`text-control py-[10px] ${className}`}
            placeholder={placeholder}
            readOnly={readonly}
            disabled={disabled}
            onChange={onChange}
            id={id}
            defaultValue={defaultValue}
            {...rest}
          />
        )}
        {/* Icons */}
        <div className="flex text-xl absolute right-[14px] top-1/2 -translate-y-1/2 space-x-1">
          {hasicon && type === 'password' && (
            <span className="cursor-pointer text-gray-400" onClick={handleOpen}>
              {open ? (
                <FontAwesomeIcon icon={faEye} />
              ) : (
                <FontAwesomeIcon icon={faEyeSlash} />
              )}
            </span>
          )}

          {error && (
            <span className="text-red-500">
              <FontAwesomeIcon icon={faInfoCircle} />
            </span>
          )}
          {validate && (
            <span className="text-green-500">
              <FontAwesomeIcon icon={faCheckCircle} />
            </span>
          )}
        </div>
      </div>
      {/* Error message */}
      {error && (
        <div className="mt-2 text-red-500 block text-sm">{error.message}</div>
      )}
      {/* Validated message */}
      {validate && (
        <div className="mt-2 text-green-500 block text-sm">{validate}</div>
      )}
      {/* Description */}
      {description && <span className="input-help">{description}</span>}
    </div>
  );
};

export default Textinput;

