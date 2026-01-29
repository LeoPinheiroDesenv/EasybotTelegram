import { Icon } from "@iconify/react/dist/iconify.js";
import React, { useState, useContext, useEffect } from 'react';
import { Link, useNavigate } from "react-router-dom";
import { AuthContext } from '../contexts/AuthContext';
import { useGoogleLogin } from '@react-oauth/google';
import MoonLoader from "react-spinners/MoonLoader";

const SignInLayer = ({
  email,
  setEmail,
  password,
  setPassword,
  twoFactorToken,
  setTwoFactorToken,
  handleSubmit,
  handleVerifyTwoFactor,
  handleGoogleLogin,
  loading,
  error,
  requiresTwoFactor,
  setRequiresTwoFactor,
  setError
}) => {
  const [showPassword, setShowPassword] = useState(false);

  return (
    <section className='auth bg-base d-flex flex-wrap'>
      <div className='auth-left d-lg-block d-none'>
        <div className='d-flex align-items-center flex-column h-100 justify-content-center'>
          <img src='/assets/images/auth/auth-img.png' alt='' />
        </div>
      </div>
      <div className='auth-right py-32 px-24 d-flex flex-column justify-content-center'>
        <div className='max-w-464-px mx-auto w-100'>
          {!requiresTwoFactor ? (
            <>
              <div>
                <Link to='/' className='mb-40 max-w-290-px'>
                  <img src='/assets/images/logo.png' alt='' />
                </Link>
                <h4 className='mb-12'>Faça login na sua conta</h4>
                <p className='mb-32 text-secondary-light text-lg'>
                  Bem-vindo de volta! Por favor, insira seus dados.
                </p>
              </div>
              <form onSubmit={handleSubmit}>
                <div className='icon-field mb-16'>
                  <span className='icon top-50 translate-middle-y'>
                    <Icon icon='mage:email' />
                  </span>
                  <input
                    type='email'
                    className='form-control h-56-px bg-neutral-50 radius-12'
                    placeholder='Email'
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    disabled={loading}
                  />
                </div>
                <div className='position-relative mb-20'>
                  <div className='icon-field'>
                    <span className='icon top-50 translate-middle-y'>
                      <Icon icon='solar:lock-password-outline' />
                    </span>
                    <input
                      type={showPassword ? 'text' : 'password'}
                      className='form-control h-56-px bg-neutral-50 radius-12'
                      id='your-password'
                      placeholder='Password'
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                      disabled={loading}
                    />
                  </div>
                  <span
                    className={`toggle-password ri-eye-${showPassword ? 'off-' : ''}line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light`}
                    onClick={() => setShowPassword(!showPassword)}
                    style={{ cursor: 'pointer', zIndex: 10 }}
                  />
                </div>
                {error && <div className="alert alert-danger">{error}</div>}
                <div className=''>
                  <div className='d-flex justify-content-between gap-2'>
                    <div className='form-check style-check d-flex align-items-center'>
                      <input
                        className='form-check-input border border-neutral-300'
                        type='checkbox'
                        defaultValue=''
                        id='remeber'
                      />
                      <label className='form-check-label' htmlFor='remeber'>
                        Lembrar-me{" "}
                      </label>
                    </div>
                    <Link to='/forgot-password' className='text-primary-600 fw-medium'>
                      Esqueceu a senha?
                    </Link>
                  </div>
                </div>
                <button
                  type='submit'
                  className='btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32 d-flex justify-content-center align-items-center'
                  disabled={loading}
                >
                  {loading ? <MoonLoader color="#ffffff" size={20} /> : 'Sign In'}
                </button>
                <div className='mt-32 center-border-horizontal text-center'>
                  <span className='bg-base z-1 px-4'>Ou faça login com</span>
                </div>
                <div className='mt-32 d-flex align-items-center gap-3'>
                  <button
                    type='button'
                    className='fw-semibold text-primary-light py-16 px-24 w-100 border radius-12 text-md d-flex align-items-center justify-content-center gap-12 line-height-1 bg-hover-primary-50'
                    onClick={handleGoogleLogin}
                    disabled={loading}
                  >
                    <Icon
                      icon='logos:google-icon'
                      className='text-primary-600 text-xl line-height-1'
                    />
                    Sign in with Google
                  </button>
                </div>
                <div className='mt-32 text-center text-sm'>
                  <p className='mb-0'>
                    Não tem uma conta?{" "}
                    <Link to='/register-admin' className='text-primary-600 fw-semibold'>
                      Cadastre-se
                    </Link>
                  </p>
                </div>
              </form>
            </>
          ) : (
            <div>
              <h4 className='mb-12'>Autenticação de Dois Fatores</h4>
              <p className='mb-32 text-secondary-light text-lg'>
                Digite o código de 6 dígitos do seu aplicativo autenticador.
              </p>
              <form onSubmit={handleVerifyTwoFactor}>
                <div className='icon-field mb-16'>
                  <span className='icon top-50 translate-middle-y'>
                    <Icon icon='solar:lock-keyhole-minimalistic-unlocked-outline' />
                  </span>
                  <input
                    type='text'
                    className='form-control h-56-px bg-neutral-50 radius-12'
                    placeholder='000000'
                    value={twoFactorToken}
                    onChange={(e) => setTwoFactorToken(e.target.value)}
                    required
                    maxLength="6"
                    disabled={loading}
                  />
                </div>
                {error && <div className="alert alert-danger">{error}</div>}
                <button
                  type='submit'
                  className='btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32 d-flex justify-content-center align-items-center'
                  disabled={loading || twoFactorToken.length !== 6}
                >
                  {loading ? <MoonLoader color="#ffffff" size={20} /> : 'Verify and Sign In'}
                </button>
                <button 
                  type="button" 
                  className="btn btn-secondary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-16" 
                  onClick={() => {
                    setRequiresTwoFactor(false);
                    setTwoFactorToken('');
                    setError('');
                  }}
                  disabled={loading}
                >
                  Back
                </button>
              </form>
            </div>
          )}
        </div>
      </div>
    </section>
  );
};

export default SignInLayer;
