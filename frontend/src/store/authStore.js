import { create } from 'zustand';

const useAuthStore = create((set) => ({
  user: (() => {
    try {
      const u = localStorage.getItem('user');
      return u && u !== 'undefined' ? JSON.parse(u) : null;
    } catch (e) {
      console.error("Error al parsear el usuario de localStorage:", e);
      return null;
    }
  })(),
  token: localStorage.getItem('token') || null,

  setAuth: (user, token) => {
    localStorage.setItem('user', JSON.stringify(user));
    localStorage.setItem('token', token);
    set({ user, token });
  },

  logout: () => {
    localStorage.removeItem('user');
    localStorage.removeItem('token');
    set({ user: null, token: null });
  },

  isAuthenticated: () => !!localStorage.getItem('token'),
}));

export default useAuthStore;
