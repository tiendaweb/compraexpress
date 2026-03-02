window.FlyerTemplateBasicos = {
    id: 'basicos',
    name: 'Básicos del Hogar',
    bgColor: '#ffffff',
    elements: [
        { id: 't_title', type: 'text', value: 'Básicos del Hogar', x: 50, y: 40, w: 500, h: 50, styles: { fontSize: '36px', fontWeight: 'bold', color: '#166534', textAlign: 'left' } },
        { id: 't_sub', type: 'text', value: 'Calidad todos los días', x: 50, y: 90, w: 500, h: 30, styles: { fontSize: '20px', color: '#6b7280', textAlign: 'left' } },
        { id: 't_img', type: 'image', src: 'https://images.unsplash.com/photo-1584308666744-24d5e478acba?auto=format&fit=crop&w=300&h=300&q=80', x: 150, y: 150, w: 300, h: 240, styles: { objectFit: 'cover' } },
        { id: 't_price', type: 'text', value: '$15.50', x: 50, y: 420, w: 220, h: 60, styles: { fontSize: '45px', fontWeight: 'bold', color: '#15803d', textAlign: 'left' } }
    ]
};
