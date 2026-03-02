(function attachFlyerTemplateCatalog(global) {
    const list = [
        global.FlyerTemplateOfertaEstrella,
        global.FlyerTemplateFinSemana,
        global.FlyerTemplateLiquidacion,
        global.FlyerTemplateBasicos,
    ].filter(Boolean);

    const catalog = list.reduce((acc, template) => {
        acc[template.id] = template;
        return acc;
    }, {});

    global.FLYER_TEMPLATE_CATALOG = catalog;
    global.FLYER_TEMPLATE_OPTIONS = list.map(({ id, name }) => ({ id, name }));
})(window);
