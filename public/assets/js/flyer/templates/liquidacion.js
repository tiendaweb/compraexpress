window.FlyerTemplateLiquidacion = {
    id: 'liquidacion',
    name: 'Liquidación Total',
    bgColor: '#fecaca',
    elements: [
        { id: 't_title', type: 'text', value: 'LIQUIDACIÓN TOTAL', x: 0, y: 20, w: 600, h: 80, styles: { fontSize: '55px', fontWeight: '900', color: '#ffffff', backgroundColor: '#b91c1c', textAlign: 'center' } },
        { id: 't_img', type: 'image', src: 'https://images.unsplash.com/photo-1578916171728-46686eac8d58?auto=format&fit=crop&w=400&h=400&q=80', x: 100, y: 120, w: 400, h: 300, styles: { objectFit: 'cover' } },
        { id: 't_price', type: 'text', value: '-50%', x: 380, y: 150, w: 200, h: 100, styles: { fontSize: '60px', fontWeight: 'bold', color: '#b91c1c', backgroundColor: '#fef08a', borderRadius: '10px', textAlign: 'center' } },
        { id: 't_desc', type: 'text', value: 'Hasta agotar stock.', x: 50, y: 450, w: 500, h: 40, styles: { fontSize: '22px', color: '#7f1d1d', textAlign: 'center' } }
    ]
};
