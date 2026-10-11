/**
 * Pestañas con el mismo comportamiento que los botones. La activa es como el
 * botón principal: fondo marino y letra blanca, que se aclara al pasar el ratón
 * sin cambiar la letra. Las inactivas se ponen blancas al pasar, como el botón
 * secundario.
 */
export const TAB_TRIGGER_CLASS = [
    'hover:bg-background hover:text-foreground dark:hover:bg-input/30',
    'data-active:bg-primary data-active:text-primary-foreground data-active:hover:bg-primary/80 data-active:hover:text-primary-foreground',
    'dark:data-active:bg-primary dark:data-active:text-primary-foreground dark:data-active:hover:bg-primary/80 dark:data-active:hover:text-primary-foreground',
].join(' ');
