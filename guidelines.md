# Travel Planner - Development Guidelines

## Project Overview

This is a modern full-stack web application built with Laravel 12 and Vue 3, using Inertia.js for seamless SPA-like experience. The project follows modern development practices with comprehensive tooling for code quality, testing, and development workflow.

## Technology Stack

### Backend
- **Laravel 12** - PHP framework with modern features
- **Laravel Fortify** - Authentication scaffolding with 2FA support
- **Laravel Wayfinder** - Route generation for frontend
- **Inertia.js Laravel** - Server-side rendering adapter
- **SQLite** - Database (development)

### Frontend
- **Vue 3** - Progressive JavaScript framework
- **TypeScript** - Type-safe JavaScript
- **Inertia.js Vue** - Client-side adapter
- **Tailwind CSS v4** - Utility-first CSS framework
- **Reka UI** - Vue component library
- **Lucide Vue** - Icon library
- **VueUse** - Vue composition utilities

### Development Tools
- **Vite** - Fast build tool and dev server
- **ESLint** - JavaScript/TypeScript linting
- **Prettier** - Code formatting
- **Laravel Pint** - PHP code style fixer
- **Pest** - PHP testing framework
- **Concurrently** - Run multiple dev processes

## Project Structure

```
travel-planner/
├── app/                          # Laravel application code
│   ├── Actions/Fortify/         # Authentication actions
│   ├── Concerns/                # Reusable traits
│   ├── Http/Controllers/        # HTTP controllers
│   ├── Models/                  # Eloquent models
│   └── Providers/               # Service providers
├── config/                      # Laravel configuration
├── database/                    # Database files
│   ├── factories/              # Model factories
│   ├── migrations/             # Database migrations
│   └── seeders/                # Database seeders
├── resources/                   # Frontend resources
│   ├── css/app.css             # Main stylesheet
│   ├── js/                     # Vue application
│   │   ├── components/         # Vue components
│   │   ├── composables/        # Vue composables
│   │   ├── layouts/            # Page layouts
│   │   ├── lib/                # Utility functions
│   │   ├── pages/              # Inertia pages
│   │   └── types/              # TypeScript definitions
│   └── views/                  # Blade templates
├── routes/                     # Route definitions
├── storage/                    # File storage
└── tests/                      # Test files
```

## Coding Standards & Conventions

### PHP (Laravel)

#### File Organization
- Follow PSR-4 autoloading standards
- Use singular model names (`User`, not `Users`)
- Controllers should be in `app/Http/Controllers/`
- Group related controllers in subdirectories (e.g., `Settings/`)

#### Code Style
- Use Laravel Pint with default Laravel preset
- Follow Laravel naming conventions:
  - Models: PascalCase (`User`)
  - Controllers: PascalCase with `Controller` suffix (`UserController`)
  - Methods: camelCase (`getUserData`)
  - Variables: camelCase (`$userData`)

#### Best Practices
- Use type hints and return types
- Leverage Laravel's built-in features (Eloquent, validation, etc.)
- Use service providers for application bootstrapping
- Implement proper error handling and validation

### TypeScript/Vue

#### File Organization
- Components in `resources/js/components/`
- Pages in `resources/js/pages/`
- Composables in `resources/js/composables/`
- Types in `resources/js/types/`
- Utilities in `resources/js/lib/`

#### Naming Conventions
- Components: PascalCase (`AppHeader.vue`)
- Files: kebab-case for non-components (`use-current-url.ts`)
- Variables/functions: camelCase (`currentUser`)
- Types/Interfaces: PascalCase (`BreadcrumbItem`)
- Constants: UPPER_SNAKE_CASE (`API_BASE_URL`)

#### Component Structure
```vue
<script setup lang="ts">
// Imports first (external, then internal)
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';

// Types and interfaces
type Props = {
    title: string;
    optional?: boolean;
};

// Props and emits
const props = withDefaults(defineProps<Props>(), {
    optional: false,
});

// Composables and reactive data
const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <!-- Template content -->
</template>
```

#### TypeScript Configuration
- Use strict type checking
- Prefer type imports: `import type { User } from '@/types'`
- Use path aliases: `@/` for `resources/js/`
- Enable consistent type imports in ESLint

### CSS/Styling

#### Tailwind CSS
- Use Tailwind v4 with CSS variables for theming
- Custom design system defined in `resources/css/app.css`
- Responsive design with mobile-first approach
- Dark mode support with custom variant: `dark:(...)`

#### Component Styling
- Use utility classes over custom CSS
- Leverage the `cn()` utility for conditional classes
- Follow the established design system colors and spacing
- Use semantic color names (`primary`, `secondary`, `muted`)

## Development Workflow

### Setup Commands
```bash
# Initial setup
composer run setup

# Development server
composer run dev

# Development with SSR
composer run dev:ssr
```

### Code Quality
```bash
# PHP linting
composer run lint
composer run test:lint

# JavaScript/TypeScript linting
npm run lint

# Code formatting
npm run format
```

### Testing
```bash
# Run all tests
composer run test

# PHP tests only
php artisan test

# Specific test
php artisan test --filter=ExampleTest
```

## Authentication & Security

### Laravel Fortify Integration
- Two-factor authentication enabled
- Password validation rules configured in `AppServiceProvider`
- Custom Fortify actions in `app/Actions/Fortify/`
- Inertia.js views for auth pages

### Security Best Practices
- Strong password requirements in production
- CSRF protection enabled
- Rate limiting configured
- Destructive commands prohibited in production

## Database Conventions

### Migrations
- Use descriptive migration names
- Include rollback methods
- Use appropriate column types and constraints

### Models
- Use Eloquent relationships
- Implement proper casting for attributes
- Use factories for testing data
- Follow Laravel naming conventions

## Component Architecture

### Vue Components
- Use Composition API with `<script setup>`
- Prefer composables for reusable logic
- Use TypeScript for type safety
- Follow single responsibility principle

### UI Components
- Base UI components in `components/ui/`
- Application-specific components in `components/`
- Use Reka UI for complex components
- Maintain consistent prop interfaces

## State Management

### Inertia.js Patterns
- Use `usePage()` for accessing shared data
- Leverage Inertia's automatic reactivity
- Handle form submissions with Inertia helpers
- Use proper error handling for forms

### Composables
- Create reusable logic in composables
- Follow Vue 3 composition patterns
- Use proper TypeScript typing
- Export composables with clear interfaces

## Build & Deployment

### Vite Configuration
- Laravel plugin for asset compilation
- Vue plugin with proper template handling
- Tailwind CSS integration
- TypeScript support with path resolution

### Environment Configuration
- Use `.env` files for environment variables
- Separate configurations for development/production
- Proper asset versioning and caching

## Performance Considerations

### Frontend
- Use Vite's code splitting
- Implement proper component lazy loading
- Optimize images and assets
- Leverage browser caching

### Backend
- Use Eloquent efficiently (avoid N+1 queries)
- Implement proper caching strategies
- Use database indexing appropriately
- Monitor query performance

## Error Handling

### Frontend
- Use Inertia's error handling
- Implement proper form validation feedback
- Handle network errors gracefully
- Provide user-friendly error messages

### Backend
- Use Laravel's exception handling
- Implement proper validation
- Log errors appropriately
- Return consistent error responses

## Documentation Standards

### Code Documentation
- Use PHPDoc for PHP methods and classes
- Use JSDoc for complex TypeScript functions
- Document component props and emits
- Include usage examples for complex components

### API Documentation
- Document all routes and their parameters
- Include request/response examples
- Document authentication requirements
- Maintain up-to-date API documentation

## Git Workflow

### Commit Messages
- Use conventional commit format
- Include clear, descriptive messages
- Reference issues when applicable
- Keep commits atomic and focused

### Branch Strategy
- Use feature branches for new development
- Follow consistent naming conventions
- Keep branches focused and short-lived
- Use pull requests for code review

## IDE Configuration

### Recommended Extensions
- **VS Code**: Vue Language Features, TypeScript, Tailwind CSS IntelliSense
- **PhpStorm**: Laravel Plugin, Vue.js Plugin

### Editor Configuration
- Use `.editorconfig` for consistent formatting
- Configure auto-formatting on save
- Enable ESLint and Prettier integration
- Set up proper TypeScript support

---

*This document should be updated as the project evolves and new patterns emerge.*