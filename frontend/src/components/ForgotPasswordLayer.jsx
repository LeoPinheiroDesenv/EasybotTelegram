import { Icon } from '@iconify/react';
import React from 'react';
import { Link } from 'react-router-dom';
import MoonLoader from "react-spinners/MoonLoader";

const ForgotPasswordLayer = ({ email, setEmail, handleSubmit, loading, error, success }) => {
    return (
        <>
            <section className="auth forgot-password-page bg-base d-flex flex-wrap">
                <div className="auth-left d-lg-block d-none">
                    <div className="d-flex align-items-center flex-column h-100 justify-content-center">
                        <img src="/assets/images/auth/forgot-pass-img.png" alt="" />
                    </div>
                </div>
                <div className="auth-right py-32 px-24 d-flex flex-column justify-content-center">
                    <div className="max-w-464-px mx-auto w-100">
                        <div>
                            <h4 className="mb-12">Esqueceu a Senha</h4>
                            <p className="mb-32 text-secondary-light text-lg">
                                Digite o endereço de e-mail associado à sua conta e nós
                                enviaremos um link para redefinir sua senha.
                            </p>
                        </div>
                        <form onSubmit={handleSubmit}>
                            <div className="icon-field">
                                <span className="icon top-50 translate-middle-y">
                                    <Icon icon="mage:email" />
                                </span>
                                <input
                                    type="email"
                                    className="form-control h-56-px bg-neutral-50 radius-12"
                                    placeholder="Digite seu Email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                    disabled={loading}
                                />
                            </div>
                            
                            {error && <div className="alert alert-danger mt-3">{error}</div>}
                            {success && <div className="alert alert-success mt-3">{success}</div>}

                            <button
                                type="submit"
                                className="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32 d-flex justify-content-center align-items-center"
                                disabled={loading}
                            >
                                {loading ? <MoonLoader color="#ffffff" size={20} /> : 'Continuar'}
                            </button>
                            <div className="text-center">
                                <Link to="/login" className="text-primary-600 fw-bold mt-24">
                                    Voltar para Login
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </>
    )
}

export default ForgotPasswordLayer
