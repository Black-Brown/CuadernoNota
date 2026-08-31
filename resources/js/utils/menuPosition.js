export function menuPosition(trigger, menu, viewport) {
  const margin = 8;
  const below = trigger.bottom + 4;
  return {
    left: Math.max(margin, Math.min(trigger.right - menu.width, viewport.width - menu.width - margin)),
    top: Math.max(margin, Math.min(
      below + menu.height <= viewport.height - margin ? below : trigger.top - menu.height - 4,
      viewport.height - menu.height - margin,
    )),
  };
}
