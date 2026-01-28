import { Icon } from "@iconify/react";
import React from "react";
import { Link } from "react-router-dom";

const SignUpLayer = ({
  name,
  setName,
  email,
  setEmail,
  password,
  setPassword,
  passwordConfirmation,
  setPasswordConfirmation,
  handleSubmit,
  loading,
  error,
  success,
}) => {
  return (
    <section className="auth bg-base d-flex flex-wrap">
      <div className="auth-left d-lg-block d-none">
        <div className="d-flex align-items-center flex-column h-100 justify-content-center">
          <img src="assets/images/auth/auth-img.png" alt="" />
        </div>
      </div>
      <div className="auth-right py-32 px-24 d-flex flex-column justify-content-center">
        <div className="max-w-464-px mx-auto w-100">
          <div>
            <Link to="/" className="mb-40 max-w-290-px">
              <img src="assets/images/logo.png" alt="" />
            </Link>
            <h4 className="mb-12">Cadastrar Novo Administrador</h4>
            <p className="mb-32 text-secondary-light text-lg">
              Preencha os detalhes para criar uma conta.
            </p>
          </div>
          <form onSubmit={handleSubmit}>
            <div className="icon-field mb-16">
              <span className="icon top-50 translate-middle-y">
                <Icon icon="f7:person" />
              </span>
              <input
                type="text"
                className="form-control h-56-px bg-neutral-50 radius-12"
                placeholder="Nome Completo"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                disabled={loading}
              />
            </div>
            <div className="icon-field mb-16">
              <span className="icon top-50 translate-middle-y">
                <Icon icon="mage:email" />
              </span>
              <input
                type="email"
                className="form-control h-56-px bg-neutral-50 radius-12"
                placeholder="Email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                disabled={loading}
              />
            </div>
            <div className="mb-20">
              <div className="position-relative ">
                <div className="icon-field">
                  <span className="icon top-50 translate-middle-y">
                    <Icon icon="solar:lock-password-outline" />
                  </span>
                  <input
                    type="password"
                    className="form-control h-56-px bg-neutral-50 radius-12"
                    placeholder="Senha"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    disabled={loading}
                  />
                </div>
              </div>
            </div>
            <div className="mb-20">
              <div className="position-relative ">
                <div className="icon-field">
                  <span className="icon top-50 translate-middle-y">
                    <Icon icon="solar:lock-password-outline" />
                  </span>
                  <input
                    type="password"
                    className="form-control h-56-px bg-neutral-50 radius-12"
                    placeholder="Confirmar Senha"
                    value={passwordConfirmation}
                    onChange={(e) => setPasswordConfirmation(e.target.value)}
                    required
                    disabled={loading}
                  />
                </div>
              </div>
            </div>
            
            {error && <div className="alert alert-danger">{error}</div>}
            {success && <div className="alert alert-success">{success}</div>}

            <button
              type="submit"
              className="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32"
              disabled={loading}
            >
              {loading ? "Cadastrando..." : "Cadastrar"}
            </button>
            
            <div className="mt-32 text-center text-sm">
              <p className="mb-0">
                Já tem uma conta?{" "}
                <Link to="/login" className="text-primary-600 fw-semibold">
                  Fazer Login
                </Link>
              </p>
            </div>
          </form>
        </div>
      </div>
    </section>
  );
};

export default SignUpLayer;
