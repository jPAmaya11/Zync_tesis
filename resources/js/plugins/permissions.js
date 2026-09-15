import { usePage } from '@inertiajs/vue3'

export default {
    install(app) {
        // Función global para templates
        app.config.globalProperties.canDo = (key) => {
            const page = usePage()
            return !!(page.props.can && page.props.can[key])
        }
        
        // Solo mixin con methods (eliminamos computed para evitar conflicto)
        app.mixin({
            methods: {
                canDo(key) {
                    const page = this.$page || usePage()
                    return !!(page.props?.can?.[key])
                }
            }
        })
    }
}
