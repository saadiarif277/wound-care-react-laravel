{
    name: 'Commission Management',
    items: [
        {
            name: 'Commission Rules',
            href: route('commission-rules.index'),
            active: route().current('commission-rules.*'),
        },
        {
            name: 'Commission Records',
            href: route('commission-records.index'),
            active: route().current('commission-records.*'),
        },
        {
            name: 'Commission Payouts',
            href: route('commission-payouts.index'),
            active: route().current('commission-payouts.*'),
        },
    ],
},
