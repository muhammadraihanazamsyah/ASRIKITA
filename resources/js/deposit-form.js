export default (types, previousItems = []) => ({
    types,
    rows: [],
    nextKey: 0,
    submitting: false,
    init() {
        const initial = Array.isArray(previousItems) && previousItems.length ? previousItems.slice(0, 50) : [{}];
        this.rows = initial.map((item) => ({ key: this.nextKey++, waste_type_id: String(item?.waste_type_id ?? ''), weight_kg: String(item?.weight_kg ?? '') }));
    },
    add() {
        if (this.rows.length < 50) this.rows.push({ key: this.nextKey++, waste_type_id: '', weight_kg: '' });
    },
    remove(key) {
        if (this.rows.length > 1) this.rows = this.rows.filter((row) => row.key !== key);
    },
    type(row) {
        return this.types.find((type) => String(type.id) === row.waste_type_id);
    },
    amount(row) {
        const type = this.type(row);
        if (!type || !/^(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,3})?$/.test(row.weight_kg)) return null;
        const [whole, fraction = ''] = row.weight_kg.split('.');
        const grams = BigInt(whole) * 1000n + BigInt(fraction.padEnd(3, '0'));
        if (grams < 1n || grams > 1000000000n) return null;
        return (grams * BigInt(type.current_buy_price) + 500n) / 1000n;
    },
    money(amount) {
        return amount === null ? '—' : 'Rp ' + BigInt(amount).toLocaleString('id-ID');
    },
    get total() {
        return this.rows.reduce((sum, row) => sum + (this.amount(row) ?? 0n), 0n);
    },
    get valid() {
        return this.rows.length > 0 && this.rows.every((row) => (this.amount(row) ?? 0n) > 0n)
            && new Set(this.rows.map((row) => row.waste_type_id)).size === this.rows.length;
    },
});
