import React from 'react';
import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { Navbar } from '../components/Layout/Navbar';
import { AuthProvider } from '../context/AuthContext';
import { BrowserRouter } from 'react-router-dom';

describe('Navbar Component', () => {
  it('renders portal branding and notification controls', () => {
    render(
      <AuthProvider>
        <BrowserRouter>
          <Navbar />
        </BrowserRouter>
      </AuthProvider>
    );

    expect(screen.getByText('AICTE FDR Portal')).toBeInTheDocument();
    expect(screen.getByText('SIH26265')).toBeInTheDocument();
  });
});
