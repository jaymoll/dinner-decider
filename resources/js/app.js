document.addEventListener('alpine:init', () => {
    Alpine.data('dinnerDatePicker', (value) => ({
        value,
        month: null,
        open: false,
        activeIso: null,
        weekDays: ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],

        init() {
            const selected = this.value ? new Date(`${this.value}T00:00:00`) : new Date()
            this.month = new Date(selected.getFullYear(), selected.getMonth(), 1)
            this.activeIso = this.value || this.isoDate(new Date())
        },

        get monthLabel() {
            return new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric' }).format(this.month)
        },

        get days() {
            const mondayOffset = (this.month.getDay() + 6) % 7
            const first = new Date(this.month.getFullYear(), this.month.getMonth(), 1 - mondayOffset)
            const today = this.isoDate(new Date())

            return Array.from({ length: 42 }, (_, index) => {
                const date = new Date(first)
                date.setDate(first.getDate() + index)
                const iso = this.isoDate(date)

                return {
                    iso,
                    day: date.getDate(),
                    current: date.getMonth() === this.month.getMonth(),
                    today: iso === today,
                    label: new Intl.DateTimeFormat('en', { dateStyle: 'full' }).format(date),
                }
            })
        },

        get displayValue() {
            if (!this.value) return 'Choose date'
            const [year, month, day] = this.value.split('-')
            return `${day}-${month}-${year}`
        },

        toggle() {
            if (this.open) {
                this.close(true)
                return
            }

            this.open = true
            this.$nextTick(() => this.focusActiveDay())
        },

        close(returnFocus = false) {
            this.open = false
            if (returnFocus) this.$nextTick(() => this.$refs.trigger.focus())
        },

        previousMonth() {
            this.month = new Date(this.month.getFullYear(), this.month.getMonth() - 1, 1)
            this.activeIso = this.days.find((day) => day.current)?.iso
            this.$nextTick(() => this.focusActiveDay())
        },

        nextMonth() {
            this.month = new Date(this.month.getFullYear(), this.month.getMonth() + 1, 1)
            this.activeIso = this.days.find((day) => day.current)?.iso
            this.$nextTick(() => this.focusActiveDay())
        },

        choose(iso) {
            this.value = iso
            this.activeIso = iso
            this.close(true)
        },

        clear() {
            this.value = ''
            this.close(true)
        },

        handleGridKeydown(event) {
            const movement = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[event.key]
            if (movement !== undefined) {
                event.preventDefault()
                this.moveFocus(movement)
                return
            }

            if (event.key === 'Home' || event.key === 'End') {
                event.preventDefault()
                const currentIndex = Math.max(0, this.days.findIndex((day) => day.iso === this.activeIso))
                this.moveFocus(event.key === 'Home' ? -(currentIndex % 7) : 6 - (currentIndex % 7))
            }
        },

        moveFocus(offset) {
            const currentIndex = Math.max(0, this.days.findIndex((day) => day.iso === this.activeIso))
            const targetIndex = Math.max(0, Math.min(41, currentIndex + offset))
            this.activeIso = this.days[targetIndex].iso
            this.$nextTick(() => this.focusActiveDay())
        },

        focusActiveDay() {
            const index = this.days.findIndex((day) => day.iso === this.activeIso)
            const buttons = Array.isArray(this.$refs.dayButtons) ? this.$refs.dayButtons : [this.$refs.dayButtons]
            buttons[index < 0 ? 0 : index]?.focus()
        },

        isoDate(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
        },
    }))
})
