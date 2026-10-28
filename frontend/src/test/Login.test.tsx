import React from 'react';
import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { BrowserRouter } from 'react-router-dom';
import { Login } from '../pages/Login';
import { AuthProvider } from '../context/AuthContext';

describe('Login Component', () => {
  it('renders AICTE portal header and quick demo personas', () => {
    render(
      <AuthProvider>
        <BrowserRouter>
          <Login />
        </BrowserRouter>
      </AuthProvider>
    );

    expect(screen.getByText('AICTE FDR Portal')).toBeInTheDocument();
    expect(screen.getByText('SIH26265', { exact: false })).toBeInTheDocument();
    expect(screen.getByText('Sign in to Portal')).toBeInTheDocument();
    expect(screen.getByText(/Quick Demo Persona Switcher/i)).toBeInTheDocument();
    expect(screen.getByText('System Admin')).toBeInTheDocument();
    expect(screen.getByText('Verification Officer')).toBeInTheDocument();
  });
});
