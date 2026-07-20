document.addEventListener('alpine:init', () => {
    Alpine.data('dinnerDatePicker', (value) => ({
        value,
        month: null,
        open: false,
        weekDays: ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],

        init() {
            const selected = this.value ? new Date(`${this.value}T00:00:00`) : new Date()
            this.month = new Date(selected.getFullYear(), selected.getMonth(), 1)
        },

        get monthLabel() {
            return new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric' }).format(this.month)
        },

        get days() {
            const mondayOffset = (this.month.getDay() + 6) % 7
            const first = new Date(this.month.getFullYear(), this.month.getMonth(), 1 - mondayOffset)

            return Array.from({ length: 42 }, (_, index) => {
                const date = new Date(first)
                date.setDate(first.getDate() + index)
                const iso = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`

                return { iso, day: date.getDate(), current: date.getMonth() === this.month.getMonth() }
            })
        },

        get displayValue() {
            if (!this.value) return 'Choose date'
            const [year, month, day] = this.value.split('-')
            return `${day}-${month}-${year}`
        },

        previousMonth() {
            this.month = new Date(this.month.getFullYear(), this.month.getMonth() - 1, 1)
        },

        nextMonth() {
            this.month = new Date(this.month.getFullYear(), this.month.getMonth() + 1, 1)
        },

        choose(iso) {
            this.value = iso
            this.open = false
        },
    }))
})
