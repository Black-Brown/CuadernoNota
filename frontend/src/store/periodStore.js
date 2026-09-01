import { create } from 'zustand';

const usePeriodStore = create((set) => ({
  selectedPeriodId: (() => {
    const value = localStorage.getItem('selectedPeriodId');
    return value ? Number(value) : null;
  })(),
  selectedPeriod: null,

  setSelectedPeriod: (period) => {
    if (period?.id) {
      localStorage.setItem('selectedPeriodId', String(period.id));
      set({ selectedPeriodId: Number(period.id), selectedPeriod: period });
      return;
    }

    localStorage.removeItem('selectedPeriodId');
    set({ selectedPeriodId: null, selectedPeriod: null });
  },
}));

export default usePeriodStore;
