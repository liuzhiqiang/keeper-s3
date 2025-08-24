import React, {useState} from "react";
import axios from "axios";
import {
    LinearProgress,
    Button,
    Box,
    Typography,
    Select,
    MenuItem,
    Grid,
    FormControl,
    InputLabel,
    TextField,
} from "@mui/material";
import {toast, ToastContainer} from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import {AdapterDayjs} from "@mui/x-date-pickers/AdapterDayjs";
import {LocalizationProvider} from "@mui/x-date-pickers/LocalizationProvider";
import {DatePicker} from '@mui/x-date-pickers/DatePicker';
import { useTranslation } from 'react-i18next';


const BatchConvertAttachments = () => {
    const { t } = useTranslation();
    const [sourceStorage, setSourceStorage] = useState("local");
    const [targetStorage, setTargetStorage] = useState("s3");
    const [startDate, setStartDate] = useState(null);
    const [endDate, setEndDate] = useState(null);
    const [progress, setProgress] = useState(0);
    const [converting, setConverting] = useState(false);
    const [isConverting, setIsConverting] = useState(false);
    const [convertedCount, setConvertedCount] = useState(0);
    const [totalCount, setTotalCount] = useState(0);
    const [batchSize, setBatchSize] = useState(10);
    const [batchInterval, setBatchInterval] = useState(5000);

    // Start batch conversion
    const startBatchConvert = async () => {
        setConverting(true);
        setProgress(0);
        setConvertedCount(0);
        setIsConverting(true);


        try {

            // Get total number of attachments
            const response = await axios.post(s3keeper_ajax_object.ajax_url, new URLSearchParams({
                action: "s3keeper_batch_convert_attachments",
                source_storage: sourceStorage,
                target_storage: targetStorage,
                _wpnonce: s3keeper_ajax_object.nonce,
                start_date: startDate ? startDate.format('YYYY-MM-DD') : null,
                end_date: endDate ? endDate.format('YYYY-MM-DD') : null,
                batch_size: batchSize,

            }));

            // console.log(response.data);
            if (response.data.success) {
                // console.log(response.data);
                setTotalCount(response.data.data.total || 0);
                setConvertedCount(response.data.data.total - (response.data.data.remaining || 0));  // Update converted count

                if (response.data.data.remaining > 0) {
                    const newProgress = Math.min(
                        ((response.data.data.total - response.data.data.remaining) / response.data.data.total) * 100,
                        100
                    );
                    setProgress(newProgress);

                    processBatch(1, response.data.data.total || 0);

                } else {
                    setProgress(100);
                    setConverting(false);
                    toast.success(t("batchConversionCompleted"));

                }


            } else {

                toast.error(response.data.data || t('batchConversionFailed'));

                setConverting(false);
                // setIsConverting(false);

            }
        } catch (error) {

            toast.error(t('batchConversionFailedCheckConsole')); // 错误消息使用 Toast
            console.error("Batch conversion error:", error);
            setConverting(false);
            // setIsConverting(false);
        }

    };

    const handleStartDateChange = (newValue) => {
        setStartDate(newValue);
    };

    const handleEndDateChange = (newValue) => {
        setEndDate(newValue);
    };

    const handleBatchSizeChange = (event) => {
        setBatchSize(parseInt(event.target.value, 10));
    };


    const handleBatchIntervalChange = (event) => {
        setBatchInterval(parseInt(event.target.value, 10));
    };


    // Process each batch of attachments
    const processBatch = async (page, allTotal) => {
        try {
            const response = await axios.post(
                s3keeper_ajax_object.ajax_url,
                new URLSearchParams({
                    action: "s3keeper_batch_convert_attachments",
                    source_storage: sourceStorage,
                    target_storage: targetStorage,
                    _wpnonce: s3keeper_ajax_object.nonce,
                    allTotal: allTotal,
                    page: page,
                    start_date: startDate ? startDate.format('YYYY-MM-DD') : null,
                    end_date: endDate ? endDate.format('YYYY-MM-DD') : null,
                    batch_size: batchSize,

                })
            );

            if (response.data.success) {
                const remaining = response.data.data.remaining || 0;
                setTotalCount(response.data.data.allTotal || allTotal);
                setConvertedCount(allTotal - remaining);

                // console.log(`remaining ${remaining}   allTotal ${allTotal}`);

                if (allTotal > 0) {
                    const newProgress = Math.min(
                        ((allTotal - remaining) / allTotal) * 100,
                        100
                    );
                    setProgress(newProgress);
                } else {
                    setProgress(100);
                }

                if (remaining > 0) {
                    setTimeout(() => processBatch(page + 1, allTotal), batchInterval);
                } else {
                    setConvertedCount(allTotal);
                    setProgress(100);
                    setConverting(false); // Conversion completed
                    // setIsConverting(false);
                    toast.success(t("batchConversionCompleted"));
                }

            } else {
                setConverting(false);
                // setIsConverting(false);
                toast.error(response.data.data ||t('anErrorOccurredDuringBatch'));
            }
        } catch (error) {
            setConverting(false);
            // setIsConverting(false);
            toast.error(response.data.data ||t('batchConversionFailedCheckConsole'));
            console.error("Batch conversion error:", error);
        }
    };

    return (
        <Box sx={{padding: 2}}>
            <Typography variant="h5" gutterBottom>
                {t('batchAttachmentConversion')}
            </Typography>
            <Grid container spacing={2} alignItems="center">
                <Grid item xs={12} sm={6}>
                    <Box sx={{marginBottom: 2}}>

                        <FormControl fullWidth>
                            <InputLabel id="source-storage-label">{t('sourceStorage')}</InputLabel>
                            <Select
                                disabled={converting}
                                labelId="source-storage-label"
                                value={sourceStorage}
                                onChange={(e) => setSourceStorage(e.target.value)}
                                label={t('sourceStorage')}
                            >
                                <MenuItem value="local">{t('localStorage')}</MenuItem>
                                <MenuItem value="s3">{t('s3Storage')}</MenuItem>
                                <MenuItem value="both">{t('localAndS3Option')}</MenuItem>
                            </Select>
                        </FormControl>
                    </Box>
                </Grid>

                <Grid item xs={12} sm={6}>
                    <Box sx={{marginBottom: 2}}>

                        <FormControl fullWidth>
                            <InputLabel id="target-storage-label">{t('targetStorage')}</InputLabel>
                            <Select
                                disabled={converting}
                                labelId="target-storage-label"
                                value={targetStorage}
                                onChange={(e) => setTargetStorage(e.target.value)}
                                label={t('targetStorage')}
                            >
                                <MenuItem value="local">{t('localStorage')}</MenuItem>
                                <MenuItem value="s3">{t('s3Storage')}</MenuItem>
                                <MenuItem value="both">{t('localAndS3Option')}</MenuItem>
                            </Select>
                        </FormControl>
                    </Box>
                </Grid>


                <Grid item xs={12} sm={6}>
                    <Box sx={{marginBottom: 2}}>
                        <LocalizationProvider dateAdapter={AdapterDayjs}>
                            <DatePicker
                                label={t('startDate')}
                                disabled={converting}
                                value={startDate}
                                onChange={handleStartDateChange}
                                renderInput={(params) => <TextField {...params} fullWidth margin="normal"/>}
                            />
                        </LocalizationProvider>
                    </Box>
                </Grid>

                <Grid item xs={12} sm={6}>
                    <Box sx={{marginBottom: 2}}>
                        <LocalizationProvider dateAdapter={AdapterDayjs}>
                            <DatePicker
                                label={t('endDate')}
                                disabled={converting}
                                value={endDate}
                                onChange={handleEndDateChange}
                                renderInput={(params) => <TextField {...params} fullWidth margin="normal"/>}
                            />
                        </LocalizationProvider>
                    </Box>
                </Grid>

                <Grid item xs={12} sm={6}>
                    <Box sx={{marginBottom: 2}}>

                        <TextField
                            label={t('batchSize')}
                            type="number"
                            value={batchSize}
                            disabled={converting}
                            onChange={handleBatchSizeChange}
                            fullWidth
                            margin="normal"
                        />
                    </Box>
                </Grid>

                <Grid item xs={12} sm={6}>
                    <Box sx={{marginBottom: 2}}>

                        <TextField
                            label={t('batchInterval')}
                            type="number"
                            value={batchInterval}
                            disabled={converting}
                            onChange={handleBatchIntervalChange}
                            fullWidth
                            margin="normal"
                        />
                    </Box>
                </Grid>

            </Grid>


            <Button
                variant="contained"
                color="primary"
                onClick={startBatchConvert}
                disabled={converting}
            >
                {converting ? t('converting') : t('startBatchConversion')}
            </Button>

            {isConverting && (
                <Box sx={{ marginTop: 2 }}>
                    <LinearProgress
                        variant="determinate"
                        value={progress}
                        sx={{ height: 10, borderRadius: 5 }}
                    />
                    {totalCount <= 0 && (
                        converting && (
                            <Typography variant="body2" sx={{marginTop: 1}}>
                                {t('preparing')}
                            </Typography>
                        )


                    )}
                    {totalCount <=0 && !converting &&(

                        <Typography variant="body2" sx={{marginTop: 1}}>
                            {t('noFiles')}
                        </Typography>
                    )}


                    {totalCount >0 && (
                        <Typography variant="body2" sx={{marginTop: 1}}>
                            {t('conversionProgress', { convertedCount, totalCount})}
                        </Typography>
                    )}

                </Box>
            )}
            <ToastContainer
                position="top-right"
                autoClose={5000}
                hideProgressBar={false}
                newestOnTop={true}
                closeButton={true}
                rtl={false}
            />
        </Box>
    );
};

export default BatchConvertAttachments;