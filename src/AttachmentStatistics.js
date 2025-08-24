import React, { useState, useEffect } from 'react';
import { Typography, Box, Grid, Card, CardContent, Container, Paper, LinearProgress } from '@mui/material';
import { Chart } from 'react-chartjs-2';
import { Chart as ChartJS, ArcElement, Tooltip, Legend,PieController  } from 'chart.js';
import axios from 'axios';
import { useTranslation } from 'react-i18next';


ChartJS.register(ArcElement, Tooltip, Legend,PieController);


const AttachmentStatistics = () => {
    const { t } = useTranslation();
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(false);

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
    };

    const chartData = stats
        ? {
            labels: [t('s3'), t('local'), t('localAndS3Option')],
            datasets: [
                {
                    label: t('attachmentStorage'),
                    data: [stats.s3, stats.local, stats.both],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)'
                    ],
                    borderWidth: 1,
                },
            ],
        } : null;


    // Fetch data from WordPress AJAX
    useEffect(() => {
        setLoading(true);
        axios.get(s3keeper_ajax_object.ajax_url, {
            params: {
                action: 's3keeper_get_attachment_statistics',
            },
        }).then(response => {
            setStats(response.data.data.data);
            setLoading(false);
        }).catch(error => {
            console.error('There was an error fetching the attachment statistics!', error);
            setLoading(false);
        });
    }, []);


    return (
        <Box>
            {loading &&  <LinearProgress sx={{width: '100%',}}/> }
            {!loading && stats &&  <Container  sx={{ mt: 4, mb: 4,border: 0}}>
                <Paper elevation={0} sx={{ padding: 3, border: 0 }}>
                    <Typography variant="h4" gutterBottom sx={{ textAlign: 'center'}}>
                        {t('attachmentStorageStatistics')}
                    </Typography>

                    <Grid container spacing={3} alignItems="center" >

                        <Grid item xs={12} md={6} sx={{display: 'flex', justifyContent: 'center'}}>

                            <Box  sx={{maxWidth: '300px', maxHeight: '300px', height: '300px',width: '300px', position:'relative'}}>

                                {chartData && <Chart type='pie' data={chartData}  options={chartOptions}  />}


                            </Box>

                        </Grid>
                        <Grid item xs={12} md={6} >

                            <Grid container spacing={2}>
                                <Grid item xs={12} sm={6}>
                                    <Card>
                                        <CardContent sx={{ textAlign: 'center' }}>
                                            <Typography variant="h6" color="textSecondary">
                                                {t('s3Attachments')}
                                            </Typography>
                                            {stats && <Typography variant="h5">{stats.s3}</Typography>}
                                        </CardContent>
                                    </Card>
                                </Grid>

                                <Grid item xs={12} sm={6}>
                                    <Card>
                                        <CardContent sx={{ textAlign: 'center' }}>
                                            <Typography variant="h6" color="textSecondary">
                                                {t('localAttachments')}
                                            </Typography>
                                            {stats && <Typography variant="h5">{stats.local}</Typography>}
                                        </CardContent>
                                    </Card>
                                </Grid>

                                <Grid item xs={12} >
                                    <Card>
                                        <CardContent sx={{ textAlign: 'center' }}>
                                            <Typography variant="h6" color="textSecondary">
                                                {t('bothAttachments')}
                                            </Typography>
                                            {stats && <Typography variant="h5">{stats.both}</Typography>}
                                        </CardContent>
                                    </Card>
                                </Grid>

                            </Grid>


                        </Grid>
                    </Grid>
                </Paper>
            </Container>
            }
        </Box>
    );
};

export default AttachmentStatistics;