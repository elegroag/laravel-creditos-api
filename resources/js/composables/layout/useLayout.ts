import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSession } from '@/composables/useSession';
import type { 
    NavItem, 
    LayoutConfig, 
    BreadcrumbItem,
    NotificationItem,
    QuickAction,
    UserMenuItem,
    SearchState,
    ThemeConfig
} from '@/types/layout';

// Estado compartido (singleton)
const sidebarOpen = ref(false);
const sidebarCollapsed = ref(false);
const userMenuOpen = ref(false);

export function useLayout() {
    const { user, isAuthenticated, clearSession } = useSession();

    // Estado del layout
    const layoutConfig = ref<LayoutConfig>({
        sidebarOpen: false,
        sidebarCollapsed: false,
        userMenuOpen: false,
        theme: 'auto',
        notifications: true
    });

    const notifications = ref<NotificationItem[]>([]);
    const breadcrumbs = ref<BreadcrumbItem[]>([]);
    const searchState = ref<SearchState>({
        query: '',
        results: [],
        loading: false,
        open: false
    });

    // Utilidades
    const abbr = (label: string): string => {
        const parts = label
            .split(' ')
            .map((s) => s.trim())
            .filter(Boolean);

        const abbr = parts
            .slice(0, 2)
            .map((p) => p[0] || '')
            .join('')
            .toUpperCase();

        return abbr || (label.trim()[0] || '').toUpperCase() || '·';
    };

    // Items de navegación
    const navItems = computed<NavItem[]>(() => {
        const items: NavItem[] = [
            { 
                label: 'Inicio', 
                to: '/dashboard', 
                abbr: abbr('Inicio'), 
                icon: '🏠',
                requiresAuth: true 
            },
            { 
                label: 'Simulador', 
                to: '/simulador', 
                abbr: abbr('Simulador'), 
                icon: '🧮',
                requiresAuth: true,
                category: 'user'
            },
            { 
                label: 'Solicitud', 
                to: '/solicitud', 
                abbr: abbr('Solicitud'), 
                icon: '📋',
                requiresAuth: true,
                category: 'user'
            },
            { 
                label: 'Mis Solicitudes', 
                to: '/mis-solicitudes', 
                abbr: abbr('Solicitudes'), 
                icon: '📄',
                requiresAuth: true,
                category: 'user'
            },
            { 
                label: 'Documentos', 
                to: '/documentos', 
                abbr: abbr('Documentos'), 
                icon: '📎',
                requiresAuth: true,
                category: 'user'
            },
            { 
                label: 'Administración', 
                abbr: abbr('Admin'), 
                icon: '⚙️',
                requiresAuth: true,
                category: 'admin',
                permissions: ['admin'],
                children: [
                    { 
                        label: 'Usuarios', 
                        to: '/admin/usuarios', 
                        abbr: abbr('Usuarios'), 
                        icon: '👥',
                        requiresAuth: true,
                        permissions: ['admin']
                    },
                    { 
                        label: 'Solicitudes', 
                        to: '/admin/solicitudes', 
                        abbr: abbr('Solicitudes'), 
                        icon: '📋',
                        requiresAuth: true,
                        permissions: ['admin']
                    },
                    { 
                        label: 'Convenios', 
                        to: '/admin/convenios', 
                        abbr: abbr('Comvenios'), 
                        icon: '🤝',
                        requiresAuth: true,
                        permissions: ['admin']
                    },
                    { 
                        label: 'Reportes', 
                        to: '/admin/reportes', 
                        abbr: abbr('Reportes'), 
                        icon: '📊',
                        requiresAuth: true,
                        permissions: ['admin']
                    }
                ]
            }
        ];

        // Filtrar items basados en permisos
        return items.filter(item => {
            if (item.requiresAuth && !isAuthenticated.value) {
                return false;
            }
            
            if (item.permissions && item.permissions.length > 0) {
                const userPermissions = user.value?.permissions || [];
                return item.permissions.some(permission => 
                    userPermissions.includes(permission)
                );
            }
            
            return true;
        });
    });

    // Quick actions
    const quickActions = computed<QuickAction[]>(() => {
        const actions: QuickAction[] = [
            {
                id: 'new-solicitud',
                label: 'Nueva Solicitud',
                description: 'Crear una nueva solicitud de crédito',
                icon: '📋',
                color: 'blue',
                handler: () => router.visit('/solicitud'),
                shortcut: 'Ctrl+N',
                requiresAuth: true
            },
            {
                id: 'simulador',
                label: 'Simulador',
                description: 'Simular un crédito',
                icon: '🧮',
                color: 'green',
                handler: () => router.visit('/simulador'),
                shortcut: 'Ctrl+S',
                requiresAuth: true
            },
            {
                id: 'perfil',
                label: 'Mi Perfil',
                description: 'Ver y editar mi perfil',
                icon: '👤',
                color: 'purple',
                handler: () => router.visit('/perfil'),
                shortcut: 'Ctrl+P',
                requiresAuth: true
            }
        ];

        return actions.filter(action => !action.requiresAuth || isAuthenticated.value);
    });

    // User menu items
    const userMenuItems = computed<UserMenuItem[]>(() => [
        {
            label: 'Mi Perfil',
            icon: '👤',
            handler: () => router.visit('/perfil')
        },
        {
            label: 'Configuración',
            icon: '⚙️',
            handler: () => router.visit('/configuracion')
        },
        {
            label: 'Ayuda',
            icon: '❓',
            handler: () => router.visit('/ayuda')
        },
        {
            label: '',
            icon: '',
            handler: () => {},
            divider: true
        },
        {
            label: 'Cerrar Sesión',
            icon: '🚪',
            handler: () => handleLogout(),
            danger: true
        }
    ]);

    // Computed properties
    const currentPath = computed(() => {
        return window.location.pathname;
    });

    const currentPage = computed(() => {
        return navItems.value.find(item => item.to === currentPath.value);
    });

    const currentPageTitle = computed(() => {
        return currentPage.value?.label || 'Dashboard';
    });

    const hasNotifications = computed(() => {
        return notifications.value.length > 0;
    });

    const unreadNotifications = computed(() => {
        return notifications.value.filter(n => !n.read);
    });

    const unreadCount = computed(() => {
        return unreadNotifications.value.length;
    });

    // Métodos de navegación
    const toggleSidebar = () => {
        layoutConfig.value.sidebarOpen = !layoutConfig.value.sidebarOpen;
        sidebarOpen.value = layoutConfig.value.sidebarOpen;
    };

    const toggleSidebarCollapse = () => {
        layoutConfig.value.sidebarCollapsed = !layoutConfig.value.sidebarCollapsed;
        sidebarCollapsed.value = layoutConfig.value.sidebarCollapsed;
    };

    const toggleUserMenu = () => {
        layoutConfig.value.userMenuOpen = !layoutConfig.value.userMenuOpen;
        userMenuOpen.value = layoutConfig.value.userMenuOpen;
    };

    const closeAllDropdowns = () => {
        layoutConfig.value.sidebarOpen = false;
        layoutConfig.value.userMenuOpen = false;
        searchState.value.open = false;
        sidebarOpen.value = false;
        userMenuOpen.value = false;
    };

    // Métodos de búsqueda
    const toggleSearch = () => {
        searchState.value.open = !searchState.value.open;
        if (searchState.value.open) {
            setTimeout(() => {
                // Focus en el input de búsqueda
                const searchInput = document.querySelector('[data-search-input]') as HTMLInputElement;
                if (searchInput) {
                    searchInput.focus();
                }
            }, 100);
        }
    };

    const performSearch = async (query: string) => {
        if (!query.trim()) {
            searchState.value.results = [];
            return;
        }

        searchState.value.query = query;
        searchState.value.loading = true;

        try {
            // Simular búsqueda - en producción esto llamaría a una API real
            await new Promise(resolve => setTimeout(resolve, 300));
            
            // Simular resultados de búsqueda
            const mockResults = [
                {
                    id: '1',
                    title: 'Crear Nueva Solicitud',
                    description: 'Inicia el proceso de solicitud de crédito',
                    type: 'action',
                    url: '/solicitud',
                    icon: '📋'
                },
                {
                    id: '2',
                    title: 'Simulador de Crédito',
                    description: 'Calcula cuotas y tasas para diferentes montos',
                    type: 'page',
                    url: '/simulador',
                    icon: '🧮'
                }
            ].filter(item => 
                    item.title.toLowerCase().includes(query.toLowerCase()) ||
                    item.description.toLowerCase().includes(query.toLowerCase())
                );

            searchState.value.results = mockResults;
        } catch (error) {
            console.error('Error en búsqueda:', error);
            searchState.value.results = [];
        } finally {
            searchState.value.loading = false;
        }
    };

    const clearSearch = () => {
        searchState.value.query = '';
        searchState.value.results = [];
        searchState.value.open = false;
    };

    // Métodos de notificaciones
    const addNotification = (notification: Omit<NotificationItem, 'id' | 'timestamp'>) => {
        const newNotification: NotificationItem = {
            id: Date.now().toString(),
            timestamp: new Date().toISOString(),
            read: false,
            ...notification
        };
        
        notifications.value.unshift(newNotification);
        
        // Limitar a 10 notificaciones
        if (notifications.value.length > 10) {
            notifications.value = notifications.value.slice(0, 10);
        }
    };

    const markNotificationAsRead = (id: string) => {
        const notification = notifications.value.find(n => n.id === id);
        if (notification) {
            notification.read = true;
        }
    };

    const markAllNotificationsAsRead = () => {
        notifications.value.forEach(n => {
            n.read = true;
        });
    };

    const removeNotification = (id: string) => {
        const index = notifications.value.findIndex(n => n.id === id);
        if (index !== -1) {
            notifications.value.splice(index, 1);
        }
    };

    // Métodos de breadcrumbs
    const updateBreadcrumbs = (items: BreadcrumbItem[]) => {
        breadcrumbs.value = items;
    };

    const addBreadcrumb = (item: BreadcrumbItem) => {
        breadcrumbs.value.push(item);
    };

    // Métodos de tema
    const toggleTheme = () => {
        const currentTheme = layoutConfig.value.theme;
        const themes: Array<'light' | 'dark' | 'auto'> = ['light', 'dark', 'auto'];
        const currentIndex = themes.indexOf(currentTheme);
        const nextIndex = (currentIndex + 1) % themes.length;
        
        layoutConfig.value.theme = themes[nextIndex];
        applyTheme(themes[nextIndex]);
    };

    const applyTheme = (theme: 'light' | 'dark' | 'auto') => {
        if (theme === 'auto') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const actualTheme = prefersDark ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', actualTheme);
        } else {
            document.documentElement.setAttribute('data-theme', theme);
        }
    };

    // Métodos de utilidad
    const handleLogout = async () => {
        try {
            await router.post('/logout');
            clearSession();
            router.visit('/login');
        } catch (error) {
            console.error('Error al cerrar sesión:', error);
        }
    };

    const handleKeydown = (event: KeyboardEvent) => {
        // Atajos de teclado
        if (event.ctrlKey || event.metaKey) {
            switch (event.key) {
                case 'b':
                    event.preventDefault();
                    toggleSidebar();
                    break;
                case 'k':
                    event.preventDefault();
                    toggleSearch();
                    break;
                case 'n':
                    if (isAuthenticated.value) {
                        event.preventDefault();
                        router.visit('/solicitud');
                    }
                    break;
                case 's':
                    if (isAuthenticated.value) {
                        event.preventDefault();
                        router.visit('/simulador');
                    }
                    break;
                case 'p':
                    if (isAuthenticated.value) {
                        event.preventDefault();
                        router.visit('/perfil');
                    }
                    break;
                case '/':
                    event.preventDefault();
                    toggleSearch();
                    break;
                case 'Escape':
                    closeAllDropdowns();
                    break;
            }
        }
    };

    // Inicialización y cleanup
    const initialize = () => {
        // Aplicar tema inicial
        applyTheme(layoutConfig.value.theme);
        
        // Agregar event listeners
        document.addEventListener('keydown', handleKeydown);
        
        // Detectar preferencia de tema del sistema
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        mediaQuery.addEventListener('change', () => {
            if (layoutConfig.value.theme === 'auto') {
                applyTheme('auto');
            }
        });

        // Actualizar breadcrumbs basado en la ruta actual
        updateBreadcrumbsFromRoute();
    };

    const updateBreadcrumbsFromRoute = () => {
        const path = currentPath.value;
        const pathSegments = path.split('/').filter(Boolean);
        
        const items: BreadcrumbItem[] = [
            { label: 'Inicio', to: '/dashboard' }
        ];

        // Construir breadcrumbs basados en la ruta
        let currentPath = '';
        for (const segment of pathSegments) {
            currentPath += '/' + segment;
            const navItem = navItems.value.find(item => item.to === currentPath);
            items.push({
                label: navItem?.label || segment,
                to: currentPath
            });
        }

        updateBreadcrumbs(items);
    };

    const cleanup = () => {
        document.removeEventListener('keydown', handleKeydown);
    };

    // Watchers
    const watchRouteChange = () => {
        updateBreadcrumbsFromRoute();
        closeAllDropdowns();
    };

    // Lifecycle hooks
    onMounted(() => {
        initialize();
    });

    onUnmounted(() => {
        cleanup();
    });

    return {
        // Estado
        layoutConfig,
        navItems,
        notifications,
        breadcrumbs,
        searchState,
        quickActions,
        userMenuItems,
        sidebarOpen,
        sidebarCollapsed,
        userMenuOpen,

        // Computed
        currentPath,
        currentPage,
        currentPageTitle,
        hasNotifications,
        unreadNotifications,
        unreadCount,

        // Métodos de navegación
        toggleSidebar,
        toggleSidebarCollapse,
        toggleUserMenu,
        closeAllDropdowns,

        // Métodos de búsqueda
        toggleSearch,
        performSearch,
        clearSearch,

        // Métodos de notificaciones
        addNotification,
        markNotificationAsRead,
        markAllNotificationsAsRead,
        removeNotification,

        // Métodos de breadcrumbs
        updateBreadcrumbs,
        addBreadcrumb,

        // Métodos de tema
        toggleTheme,
        applyTheme,

        // Utilidades
        handleLogout,
        initialize,
        cleanup,
        watchRouteChange
    };
}
