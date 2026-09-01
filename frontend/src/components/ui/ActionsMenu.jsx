import React, { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { menuPosition } from '../../utils/menuPosition';

export default function ActionsMenu({ items }) {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  const menuRef = useRef(null);
  const triggerRef = useRef(null);
  const menuId = useId();
  const [position, setPosition] = useState({ top: 0, left: 0 });

  const close = (restoreFocus = false) => {
    setOpen(false);
    if (restoreFocus) triggerRef.current?.focus();
  };

  useLayoutEffect(() => {
    if (!open) return;
    const trigger = triggerRef.current.getBoundingClientRect();
    const menu = menuRef.current.getBoundingClientRect();
    setPosition(menuPosition(trigger, menu, { width: window.innerWidth, height: window.innerHeight }));
    menuRef.current.querySelector('button:not(:disabled)')?.focus();
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const handleClick = (event) => { if (!ref.current?.contains(event.target) && !menuRef.current?.contains(event.target)) close(); };
    const handleKey = (event) => { if (event.key === 'Escape') { event.preventDefault(); close(true); } };
    const handleScroll = (event) => { if (!menuRef.current?.contains(event.target)) close(); };
    const handleResize = () => close();
    document.addEventListener('mousedown', handleClick);
    window.addEventListener('keydown', handleKey);
    window.addEventListener('scroll', handleScroll, true);
    window.addEventListener('resize', handleResize);
    return () => {
      document.removeEventListener('mousedown', handleClick);
      window.removeEventListener('keydown', handleKey);
      window.removeEventListener('scroll', handleScroll, true);
      window.removeEventListener('resize', handleResize);
    };
  }, [open]);

  const visibleItems = items.filter((item) => !item.hidden);

  return (
    <div className="relative inline-block text-left" ref={ref}>
      <button
        type="button"
        ref={triggerRef}
        aria-label="Abrir acciones"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={(event) => { event.stopPropagation(); setOpen((v) => !v); }}
        onKeyDown={(event) => { if (event.key === 'ArrowDown') { event.preventDefault(); setOpen(true); } }}
        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-800"
      >
        <span className="material-symbols-outlined text-[18px]">more_horiz</span>
      </button>

      {open && createPortal(
        <div
          ref={menuRef}
          id={menuId}
          role="menu"
          aria-label="Acciones del registro"
          style={position}
          onClick={(event) => event.stopPropagation()}
          onKeyDown={(event) => {
            if (event.key === 'Tab') { close(true); return; }
            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const buttons = Array.from(menuRef.current.querySelectorAll('button:not(:disabled)'));
            if (!buttons.length) return;
            const current = buttons.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (current + (event.key === 'ArrowUp' ? -1 : 1) + buttons.length) % buttons.length;
            buttons[next].focus();
          }}
          className="fixed z-[80] max-h-[calc(100dvh-16px)] w-44 max-w-[calc(100vw-16px)] overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 text-left shadow-lg"
        >
          {visibleItems.map((item) => (
            <button
              key={item.label}
              role="menuitem"
              type="button"
              disabled={item.disabled}
              onClick={() => { close(true); item.onClick(); }}
              className={`flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-40 ${
                item.danger ? 'text-red-600 hover:bg-red-50' : 'text-slate-700 hover:bg-slate-50'
              }`}
            >
              <span className="material-symbols-outlined text-[16px]">{item.icon}</span>
              {item.label}
            </button>
          ))}
        </div>, document.body
      )}
    </div>
  );
}
